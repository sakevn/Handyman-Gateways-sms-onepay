<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;

class TapPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $secret_key;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('tap_payment', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->secret_key = $this->config_values->secret_key;
            $this->config_mode = ($config->mode == 'test') ? 'test' : 'live';
        }

        $this->payment = $payment;
    }

    public function payment(Request $req): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($req->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment_data = $this->payment::where(['id' => $req['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($payment_data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($payment_data['payer_information']);

        if (in_array($payment_data->currency_code, ['AED', 'BHD', 'EGP', 'EUR', 'GBP', 'KWD', 'OMR', 'QAR', 'SAR', 'USD'])) {
            $secret_key = $this->secret_key;
            $data['amount'] = $payment_data->payment_amount;
            $data['order_id'] = $payment_data->attribute_id;
            $data['user_id'] = $payment_data->payer_id;
            $data['live_mode'] = $this->config_mode == 'live';
            $data['currency'] = $payment_data->currency_code;
            $data['customer']['first_name'] = $payer->name;
            $data['customer']['last_name'] = $payer->name;
            $data['customer']['country_code'] = substr_replace($payer->phone, "", -10);
            $data['customer']['phone'] = $payer->phone;
            $data['customer']['email'] = $payer->email;
            $data['source']['id'] = "src_card";
            $data['redirect']['url'] = route('tap.callback', ['payment_id' => $req['payment_id']]);
            $headers = [
                "authorization: Bearer " . $secret_key,
                "content-type: application/json"
            ];


            $ch = curl_init();
            $url = "https://api.tap.company/v2/charges";
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($ch);
            curl_close($ch);
            $response = json_decode($output);
            return redirect()->to($response->transaction->url);
        } else {
            return back();
        }
    }

    public function callback(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $secret_key = $this->secret_key;
        $headers = [
            "authorization: Bearer " . $secret_key,
            "content-type: application/json"
        ];
        $ch = curl_init();
        $url = "https://api.tap.company/v2/charges/" . $request['tap_id'];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        $response = json_decode($output);

        if ($response->status == "CAPTURED") {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'tap',
                'is_paid' => 1,
                'transaction_id' => $response->reference->payment,
            ]);

            $data = $this->payment::where(['id' => $request['payment_id']])->first();

            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }
            return $this->payment_response($data, 'success');
        }
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }
}
