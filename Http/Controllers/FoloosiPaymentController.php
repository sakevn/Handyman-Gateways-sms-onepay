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
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;

class FoloosiPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $merchant_key;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('foloosi', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->merchant_key = $this->config_values->merchant_key;
            $this->config_mode = ($config->mode == 'test') ? 'test' : 'live';
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

        $data = $this->payment::where(['id' => $req['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($data['payer_information']);

        $currency = "AED";
        $customer_name = $payer->name;
        $customer_email = $payer->email;
        $customer_phone = preg_replace('/^\+?971|\D/', '', ($payer->phone));
        $merchant_key = $this->merchant_key;
        $site_return_url = urlencode(route('foloosi.callback', ['payment_id' => $data->id]));
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.foloosi.com/aggregatorapi/web/initialize-setup",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "transaction_amount=" . $data->payment_amount . "&currency=" . $currency . "&optional1=" . $req['payment_id'] . "&customer_name=" . $customer_name . "&customer_email=" . $customer_email . "&customer_mobile=" . $customer_phone . "&site_return_url=" . $site_return_url,
            CURLOPT_HTTPHEADER => array(
                'content-type: application/x-www-form-urlencoded',
                'merchant_key: ' . $this->merchant_key
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return response()->json(['message' => $err], 403);
        } else {
            $responseData = json_decode($response, true);
            $reference_token = $responseData['data']['reference_token'];
            return view('Gateways::payment.foloosi', compact('reference_token', 'merchant_key'));
        }
    }

    public function callback(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        if ($request->status == 'success') {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'foloosi',
                'is_paid' => 1,
                'transaction_id' => $request->transaction_no,
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
