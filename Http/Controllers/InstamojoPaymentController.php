<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Modules\Gateways\Traits\Processor;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Entities\PaymentRequest;

class InstamojoPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private string $base_url;
    private $client_id;
    private $client_secret;

    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('instamojo', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->base_url = ($config->mode == 'test') ? 'https://test.instamojo.com' : 'https://api.instamojo.com';
            $this->client_id = $this->config_values->client_id;
            $this->client_secret = $this->config_values->client_secret;
        }
        $this->payment = $payment;
    }

    private function access_token()
    {
        $ch = curl_init();
        $url = $this->base_url . '/oauth2/token/';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

        $payload = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret
        );

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $result = curl_exec($ch);

        curl_close($ch);
        $response = json_decode($result);
        $accessToken = $response['access_token'];
        return $accessToken;
    }

    /**
     * @param Request $req
     * @return Application|\Illuminate\Foundation\Application|JsonResponse|RedirectResponse|Redirector
     */
    public function payment(Request $req): \Illuminate\Foundation\Application|JsonResponse|Redirector|RedirectResponse|Application
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
        $payer_information = json_decode($data['payer_information']);

        $ch = curl_init();

        $dynamicBearerToken = 'Bearer ' . $this->access_token();
        $url = $this->base_url . '/v2/payment_requests/';

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: $dynamicBearerToken"));

        $payload = array(
            'purpose' => 'purpose' . strval(rand(343, 4555)),
            'amount' => $data->payment_amount,
            'buyer_name' => $payer_information->name,
            'email' => $payer_information->email,
            'phone' => $payer_information->phone,
            'redirect_url' => url("/payment/instamojo/callback/?payment_data_id={$data->id}"),
            'send_email' => 'True',
            'webhook' => '',
            'allow_repeated_payments' => 'False',
        );

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return redirect($response->payment_request->longurl);
    }

    /**
     * @param Request $request
     * @return Application|\Illuminate\Foundation\Application|JsonResponse|RedirectResponse|Redirector
     */
    public function callback(Request $request): \Illuminate\Foundation\Application|JsonResponse|Redirector|RedirectResponse|Application
    {
        $ch = curl_init();

        $dynamicBearerToken = 'Bearer ' . $this->access_token();

        $url = $this->base_url . '/v2/payments/' . $request->get('payment_id');

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: $dynamicBearerToken"));

        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $payment_data = $this->payment::where(['id' => $request['payment_data_id']])->first();
            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }
            return $this->payment_response($payment_data, 'fail');
        } else {
            $response = json_decode($result);
        }

        if ($response && $response->status == true) {
            $this->payment::where(['id' => $request['payment_data_id']])->update([
                'payment_method' => 'instamojo',
                'is_paid' => 1,
                'transaction_id' => $response->id,
            ]);
            $data = $this->payment::where(['id' => $request['payment_data_id']])->first();
            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }
            return $this->payment_response($data, 'success');
        }
        $payment_data = $this->payment::where(['id' => $request['payment_data_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }
}
