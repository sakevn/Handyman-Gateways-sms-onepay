<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;

class HubtelPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $account_number;
    private $api_id;
    private $api_key;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('hubtel_payment', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->account_number = $this->config_values->account_number;
            $this->api_id = $this->config_values->api_id;
            $this->api_key = $this->config_values->api_key;
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

        $payment_data = $this->payment::where(['id' => $req['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($payment_data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $accountNumber = $this->account_number;
        $apiId = $this->api_id;
        $apiKey = $this->api_key;

        $curl = curl_init();

        $payload = array(
            'totalAmount' => (string)$payment_data->payment_amount,
            'description' => 'dffsf',
            'callbackUrl' => route('hubtel.callback', ['payment_id' => $payment_data->id]),
            'merchantAccountNumber' => $accountNumber,
            'returnUrl' => route('hubtel.success', ['payment_id' => $payment_data->id]),
            'cancellationUrl' => route('hubtel.cancel', ['payment_id' => $payment_data->id]),
            'clientReference' => (string)$payment_data->attribute_id
        );

        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => [
                'accept' => 'application/json',
                "Content-Type: application/json",
                "Authorization: Basic " . base64_encode($apiId . ':' . $apiKey),

            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_URL => "https://payproxyapi.hubtel.com/items/initiate",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
        ]);

        $response = json_decode(curl_exec($curl), true);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            echo "cURL Error #:" . $error;
        } else {
            if (isset($response['status']) && $response['status'] == 'Success') {

                return Redirect::away($response['data']['checkoutUrl']);
            } else if (isset($response['status']) && $response['status'] == 'Error') {
                return response()->json($response['data'][0]['errorMessage']);
            }
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);

        }

        return 0;
    }

    public function callback(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        if ($request->status == 'success') {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'hubtel',
                'is_paid' => 1,
                'transaction_id' => $request->ClientReference,
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

    public function success(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $this->payment::where(['id' => $request['payment_id']])->update([
            'payment_method' => 'hubtel',
            'is_paid' => 1,
            'transaction_id' => $request->checkoutid,
        ]);

        $data = $this->payment::where(['id' => $request['payment_id']])->first();

        if (isset($data) && function_exists($data->success_hook)) {
            call_user_func($data->success_hook, $data);
        }

        return $this->payment_response($data, 'success');
    }

    public function cancel(Request $request, $order_id): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'cancel');
    }
}
