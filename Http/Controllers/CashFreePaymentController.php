<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Traits\Processor;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Entities\PaymentRequest;

class CashFreePaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private string $base_url;
    private string $production_status;
    private $client_id;
    private $client_secret;

    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('cashfree', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->base_url = ($config->mode == 'test') ? 'https://sandbox.cashfree.com/pg' : 'https://api.cashfree.com/pg';
            $this->production_status = ($config->mode == 'test') ? 'sandbox' : 'production';
            $this->client_id = $this->config_values->client_id;
            $this->client_secret = $this->config_values->client_secret;
        }
        $this->payment = $payment;
    }

    /**
     * @param Request $req
     * @return Application|Factory|View|\Illuminate\Foundation\Application|JsonResponse|void
     */
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

        $url = $this->base_url . '/orders';
        $amount = $data->payment_amount;
        $payer_information = json_decode($data['payer_information']);

        $info_data = [
            'customer_details' => [
                'customer_id' => strval(rand(100000, 9999999)),
                'customer_phone' => $payer_information->phone,
            ],
            'order_meta' => [
                'return_url' => url("/payment/cashfree/callback/?order_id={order_id}&&?payment_id={$data->id}"),
            ],
            'order_id' => $data['attribute_id'],
            'order_currency' => $data->currency_code ?? 'INR',
            'order_amount' => $amount,
        ];

        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'x-api-version: 2022-09-01',
            'x-client-id:' . $this->client_id,
            'x-client-secret:' . $this->client_secret,
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($info_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_status >= 400) {
            $response_data = json_decode($response);
            dump($response_data->message);
        } else {
            $response_data = json_decode($response);
            $session_id = $response_data->payment_session_id;
            $order_id = $response_data->order_id;
            $production_status = $this->production_status;
            return view('Gateways::payment.cash-free', compact('data', 'session_id', 'order_id', 'production_status'));
        }
    }

    /**
     * @param Request $request
     * @return Application|\Illuminate\Foundation\Application|JsonResponse|RedirectResponse|Redirector
     */
    public function callback(Request $request): \Illuminate\Foundation\Application|JsonResponse|Redirector|RedirectResponse|Application
    {
        $url = $this->base_url . '/orders' . '/' . $request->order_id;
        $headers = [
            'Content-Type: application/json',
            'x-api-version: 2022-09-01',
            'x-client-id:' . $this->client_id,
            'x-client-secret:' . $this->client_secret,
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);

        $response = json_decode($result);

        if ($response && $response->order_status == 'PAID') {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'cashfree',
                'is_paid' => 1,
                'transaction_id' => $response->cf_order_id,
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
