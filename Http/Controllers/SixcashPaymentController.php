<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;

class SixcashPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $public_key;
    private $secret_key;
    private $merchant_number;
    private $base_url;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('sixcash', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->public_key = $this->config_values->public_key;
            $this->secret_key = $this->config_values->secret_key;
            $this->merchant_number = $this->config_values->merchant_number;
            $this->base_url = $this->config_values->base_url;
            $this->config_mode = ($config->mode == 'test') ? 'test' : 'live';
        }

        $this->payment = $payment;
    }

    public function payment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $req['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $url = $this->base_url . '/api/v1/create-payment-order';
        $amount = $data->payment_amount;

        $response = Http::post($url, [
            'public_key' => $this->public_key,
            'secret_key' => $this->secret_key,
            'merchant_number' => $this->merchant_number,
            'amount' => $amount,
        ])->json();

        session()->put('payment_id', $data['id']);

        if ($response['status'] == 'merchant_not_found') {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ($response['status'] == 'payment_created') {
            return redirect()->away($response['redirect_url']);
        }
        return 0;
    }

    public function callback(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $transaction_id = $request->transaction_id;
        $payment_verification_url = $this->base_url . '/api/v1/payment-verification';

        $response = Http::post($payment_verification_url, [
            'public_key' => $this->public_key,
            'secret_key' => $this->secret_key,
            'merchant_number' => $this->merchant_number,
            'transaction_id' => $transaction_id,
        ])->json();

        if (isset($response['payment_record']['is_paid']) && $response['payment_record']['is_paid'] == 1) {
            $this->payment::where(['id' => session()->get('payment_id')])->update([
                'payment_method' => 'sixcash',
                'is_paid' => 1,
                'transaction_id' => $request->transaction_no,
            ]);

            $data = $this->payment::where(['id' => session()->get('payment_id')])->first();

            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }

            return $this->payment_response($data, 'success');
        }
        $payment_data = $this->payment::where(['id' => session()->get('payment_id')])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }
}
