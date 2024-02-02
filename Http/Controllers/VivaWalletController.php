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

class VivaWalletController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $client_id;
    private $client_secret;
    private $source_code;
    private string $token;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('viva_wallet', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->client_id = $this->config_values->client_id;
            $this->client_secret = $this->config_values->client_secret;
            $this->source_code = $this->config_values->source_code;
            $this->config_mode = ($config->mode == 'test') ? 'test' : 'live';
            $this->token = base64_encode($this->client_id . ':' . $this->client_secret);
        }

        $this->payment = $payment;
    }

    public function credential_check(): bool|string
    {
        if ($this->config_mode == 'test') {
            $token_url = 'https://demo-accounts.vivapayments.com/connect/token';
        } else {
            $token_url = 'https://accounts.vivapayments.com/connect/token';
        }
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $token_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $this->token
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $accessToken = curl_exec($curl);

        curl_close($curl);
        return $accessToken;
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

        $payer = json_decode($payment_data['payer_information']);

        if ($this->config_mode == 'test') {

            $order_code_url = 'https://demo-api.vivapayments.com/checkout/v2/orders';
            $transcation_url = 'https://demo.vivapayments.com/web/checkout?ref=';

        } else {
            $order_code_url = 'https://api.vivapayments.com/checkout/v2/orders';
            $transcation_url = 'https://www.vivapayments.com/web/checkout?ref=';
        }

        $accessToken = json_decode($this->credential_check(), true);

        if (isset($accessToken['access_token'])) {

            try {
                $accessToken = $accessToken['access_token'];
                $amount = round($payment_data->payment_amount * 100, 2);
                $bill_amount = round($payment_data->payment_amount, 2);
                $postFields = [
                    'amount' => $amount,
                    'customerTrns' => 'trx-' . $bill_amount,
                    'customer' => [
                        'email' => $payer->email,
                        'fullName' => $payer->name,
                        'phone' => $payer->phone,
                        'countryCode' => 'UK',
                        'requestLang' => 'en'
                    ],
                    'paymentTimeout' => 1800,
                    'preauth' => false,
                    'allowRecurring' => true,
                    'maxInstallments' => 0,
                    'paymentNotification' => true,
                    'disableExactAmount' => false,
                    'disableCash' => false,
                    'disableWallet' => false,
                    'sourceCode' => $this->source_code ?? 'Default',
                    'merchantTrns' => $payment_data->attribute_id,
                ];

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $order_code_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($postFields),
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: Bearer $accessToken",
                        'Content-Type: application/json'
                    ),
                ));

                $response = curl_exec($curl);

                curl_close($curl);
                $data = json_decode($response);

                $this->payment::where(['id' => $req['payment_id']])->update([
                    'transaction_id' => $data->orderCode,
                ]);

                return Redirect::away($transcation_url . $data->orderCode);
            } catch (\Exception $ex) {

            }
        } else {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        return 0;
    }

    public function success(Request $request)
    {
        $transcation = $request->t;
        $order_code = $request->s;

        if ($transcation && $order_code) {
            $this->payment::where(['transaction_id' => $order_code])->update([
                'payment_method' => 'viva_wallet',
                'is_paid' => 1,
                'transaction_id' => $transcation,
            ]);

            $data = $this->payment::where(['transaction_id' => $transcation])->first();

            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }

            return $this->payment_response($data, 'success');
        }

        return 0;
    }

    public function fail(Request $request): Application|JsonResponse|int|Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $order_code = $request->s;
        if ($order_code) {
            $payment_data = $this->payment::where(['transaction_id' => $order_code])->first();
            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }
            return $this->payment_response($payment_data, 'fail');
        }
        return 0;
    }
}
