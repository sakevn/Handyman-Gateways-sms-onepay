<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use InvalidArgumentException;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;

class MaxiCashController extends Controller
{
    use Processor;

    private mixed $config_values;
    private string $config_mode = 'test';
    private $merchantId;
    private $merchantPassword;
    private array $supported_currencies = ['USD' => 'maxiDollar', 'ZAR' => 'maxiRand'];
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('maxicash', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->merchantId = $this->config_values->merchantId;
            $this->merchantPassword = $this->config_values->merchantPassword;
            $this->config_mode = ($config->mode == 'test') ? 'test' : 'live';
        }

        $this->payment = $payment;
    }

    public function index(Request $req): JsonResponse|RedirectResponse
    {

        $validator = Validator::make($req->all(), [
            'payment_id' => 'required|uuid',
            'tel' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment_data = $this->payment::where(['id' => $req['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($payment_data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $telephone = $req->tel;

        $data1 = [
            "PayType" => "MaxiCash",
            "MerchantID" => $this->merchantId,
            "MerchantPassword" => $this->merchantPassword,
            "Amount" => (string)($payment_data->payment_amount * 100),
            "Currency" => $this->supported_currencies[$payment_data->currency_code],
            "Telephone" => $telephone,
            "Language" => "en",
            "Reference" => (string)$payment_data->attribute_id,
            "accepturl" => route('maxicash.callback', ['payment_id' => $payment_data->id, 'status' => 'success']),
            "declineurl" => route('maxicash.callback', ['payment_id' => $payment_data->id, 'status' => 'failed']),
            "cancelurl" => route('maxicash.callback', ['payment_id' => $payment_data->id, 'status' => 'failed']),
            "notifyurl" => route('maxicash.callback', ['payment_id' => $payment_data->id, 'status' => 'failed'])
        ];
        $data = json_encode($data1);

        $url = 'https://api-testbed.maxicashapp.com/payentry?data=' . $data;
        if ($this->config_mode == 'live') {
            $url = 'https://api.maxicashapp.com/payentry?data=' . $data;
        }

        return Redirect::to($url);
    }

    public function payment(Request $req): View|\Illuminate\Foundation\Application|Factory|JsonResponse|Application
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

        if (!array_key_exists($payment_data->currency_code, $this->supported_currencies)) {
            throw new InvalidArgumentException(translate('Your currency is not supported'));
        }

        if ($payment_data->currency_code == 'ZAR' && $payment_data->payment_amount <= 50) {
            throw new InvalidArgumentException(translate('The transaction cannot be lower than 50 rands'));
        }

        return view('Gateways::payment.maxicash', compact('payment_data'));
    }

    public function callback(Request $request): \Illuminate\Foundation\Application|JsonResponse|Redirector|Application|RedirectResponse
    {
        if ($request->status == 'success') {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'maxicash',
                'is_paid' => 1,
                'transaction_id' => $request['payment_id'],
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
