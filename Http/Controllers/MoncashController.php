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

class MoncashController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $client_id;
    private $secret_key;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('moncash', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->client_id = $this->config_values->client_id;
            $this->secret_key = $this->config_values->secret_key;
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

        $transaction_id = "trx_" . time();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://{$this->client_id}:{$this->secret_key}@moncashbutton.digicelgroup.com/Api/oauth/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "scope=read,write&grant_type=client_credentials&client_id={$this->client_id}&client_secret={$this->secret_key}");

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);

        $response = json_decode($result, true);
        $token = $response['access_token'];
        curl_close($ch);


        $ch2 = curl_init();

        curl_setopt($ch2, CURLOPT_URL, 'https://moncashbutton.digicelgroup.com/Api/v1/CreatePayment');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch2, CURLOPT_POST, 1);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, "{\"amount\": {$payment_data->payment_amount}, \"orderId\": {$payment_data->attribute_id},\"transactionId\": {$transaction_id}}");

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);

        $result2 = curl_exec($ch2);
        $response2 = json_decode($result2, true);

        $payment_token = $response2['payment_token']['token'];

        curl_close($ch2);
        $url = "https://moncashbutton.digicelgroup.com/Moncash-middleware/Payment/Redirect?token={$payment_token}";

        $this->payment::where(['id' => $req['payment_id']])->update([
            'transaction_id' => $transaction_id,
        ]);
        return redirect()->to($url);
    }

    public function callback(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $client_id = $this->client_id;
        $secret_key = $this->secret_key;
        $transactionId = $request->transactionId;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://{$client_id}:{$secret_key}@moncashbutton.digicelgroup.com/Api/oauth/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "scope=read,write&grant_type=client_credentials&client_id={$client_id}&client_secret={$secret_key}");

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $response = json_decode($result, true);
        $token = $response['access_token'];
        curl_close($ch);

        $ch2 = curl_init();

        curl_setopt($ch2, CURLOPT_URL, 'https://moncashbutton.digicelgroup.com/Api/v1/RetrieveTransactionPayment');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch2, CURLOPT_POST, 1);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, "{\"transactionId\": {$transactionId}}");

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
        $result2 = curl_exec($ch2);
        $response2 = json_decode($result2, true);
        curl_close($ch2);
        if ($response2['status'] == '200' && $response2['payment']['message'] == 'successful') {
            $this->payment::where(['transaction_id' => $transactionId])->update([
                'payment_method' => 'moncash',
                'is_paid' => 1,
                'transaction_id' => $response2['payment']['transaction_id'],
            ]);

            $payment_data = $this->payment::where(['transaction_id' => $response2['payment']['transaction_id']])->first();

            if (isset($payment_data) && function_exists($payment_data->success_hook)) {
                call_user_func($payment_data->success_hook, $payment_data);
            }
            return $this->payment_response($payment_data, 'success');
        }
        $payment_data = $this->payment::where(['id' => session('payment_id')])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }
}
