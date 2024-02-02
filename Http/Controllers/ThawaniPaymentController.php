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

class ThawaniPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $public_key;
    private $private_key;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('thawani', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->public_key = $this->config_values->public_key;
            $this->private_key = $this->config_values->private_key;
            $this->config_mode = ($config->mode == 'test') ? 'test' : 'live';
        }

        $this->payment = $payment;
    }

    public function checkout(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment_data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($payment_data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($payment_data['payer_information']);

        if ($payment_data['additional_data'] != null) {
            $business = json_decode($payment_data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
        } else {
            $business_name = "my_business";
        }

        $url = $this->config_mode == 'test' ? 'https://uatcheckout.thawani.om/api/v1/checkout/session' : 'https://checkout.thawani.om/api/v1/checkout/session';
        $data['client_reference_id'] = $payment_data->id;
        $data['mode'] = "payment";
        $data['products'] = [
            [
                'name' => $business_name,
                'quantity' => 1,
                'unit_amount' => $payment_data->payment_amount * 1000
            ]
        ];
        $data['success_url'] = route('thawani.success', ['payment_id' => $request['payment_id']]);
        $data['cancel_url'] = route('thawani.cancel', ['payment_id' => $request['payment_id']]);
        $data['metadata']['customer_id'] = $payment_data->payer_id;
        $data['metadata']['customer_name'] = $payer->name;
        $data['metadata']['order_id'] = $payment_data->attribute_id;


        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "thawani-api-key:" . $this->private_key
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return response()->json(['message' => json_decode($err)], 403);
        } else {
            $session_id = json_decode($response)->data->session_id;
            session()->put('session_id', $session_id);

            $checkoutUrls = $this->config_mode == "test" ? 'https://uatcheckout.thawani.om/pay/' . $session_id . '?key=' . $this->public_key : 'https://checkout.thawani.om/pay/' . $session_id . '?key=' . $this->public_key;
            return redirect()->to($checkoutUrls);
        }
    }

    public function success(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $session_id = session()->get('session_id');
        $response = $this->transactionStatus($session_id);
        if ($response->data->payment_status == 'paid') {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'thawani',
                'is_paid' => 1,
                'transaction_id' => $response->data->client_reference_id,
            ]);

            $data = $this->payment::where(['id' => $request['payment_id']])->first();

            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }
            session()->forget('session_id');
            return $this->payment_response($data, 'success');
        }
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }

    public function cancel(Request $request): Application|JsonResponse|int|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $session_id = session()->get('session_id');
        $response = $this->transactionStatus($session_id);
        if ($response->data->payment_status == 'cancelled') {

            $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
            session()->forget('session_id');
            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }
            return $this->payment_response($payment_data, 'cancel');
        }
        return 0;
    }

    public function transactionStatus($session_id)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->config_mode == 'test' ? "https://uatcheckout.thawani.om/api/v1/checkout/session/" . $session_id : "https://checkout.thawani.om/api/v1/checkout/session/" . $session_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "thawani-api-key:" . $this->private_key
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return response()->json($err);
        } else {
            return json_decode($response);
        }
    }
}
