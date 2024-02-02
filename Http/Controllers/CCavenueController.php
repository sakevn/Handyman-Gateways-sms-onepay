<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Modules\Gateways\Library\CryptoCCavenue as Crypto;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;

class CCavenueController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $merchant_id;
    private $working_key;
    private $access_code;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('ccavenue', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->merchant_id = $this->config_values->merchant_id;
            $this->working_key = $this->config_values->working_key;
            $this->access_code = $this->config_values->access_code;
        }

        $this->payment = $payment;
    }

    public function payment(Request $req): View|Application|Factory|JsonResponse|\Illuminate\Contracts\Foundation\Application
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

        error_reporting(0);

        $data = [
            'merchant_id' => $this->merchant_id,
            'order_id' => $payment_data->attribute_id,
            'amount' => $payment_data->payment_amount,
            'currency' => 'AED',
            'redirect_url' => route('ccavenue.payment-response', ['payment_id' => $payment_data->id]),
            'cancel_url' => route('ccavenue.payment-response', ['payment_id' => $payment_data->id]),
            'language' => 'EN',
        ];

        $merchant_data = '';
        $working_key = $this->working_key;
        $access_code = $this->access_code;

        foreach ($data as $key => $value) {
            $merchant_data .= $key . '=' . $value . '&';
        }
        $encrypted_data = Crypto::cc_encrypt($merchant_data, $working_key);
        return view('Gateways::payment.ccavenue-payment', compact(['access_code', 'encrypted_data']));

    }

    public function payment_response_process(Request $request): Application|JsonResponse|Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        error_reporting(0);
        $working_key = $this->working_key;
        $encResponse = $request->encResp;
        $rcvdString = Crypto::cc_decrypt($encResponse, $working_key);
        $order_status = "";
        $decryptValues = explode('&', $rcvdString);
        $dataSize = sizeof($decryptValues);
        for ($i = 0; $i < $dataSize; $i++) {
            $information = explode('=', $decryptValues[$i]);
            if ($i == 0) $order_id = $information[1];
            if ($i == 3) $order_status = $information[1];
            if ($i == 2) $bank_ref_no = $information[1];
        }
        if ($order_status === "Success") {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'ccavenue',
                'is_paid' => 1,
                'transaction_id' => $bank_ref_no,
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

    public function payment_cancel(Request $request): Application|JsonResponse|Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }
}
