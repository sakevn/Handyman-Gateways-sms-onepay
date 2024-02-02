<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;

class FatoorahPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;

    private PaymentRequest $payment;
    private $user;
    private $api_key;
    private string $base_url;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('fatoorah', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        $country_code = 'SA';

        if ($config) {
            $this->api_key = $this->config_values->api_key;
        }

        if (env('APP_DEBUG')) {
            $this->base_url = 'https://apitest.myfatoorah.com/v2/';
        } else if ($country_code == 'SA') {
            $this->base_url = 'https://api-sa.myfatoorah.com/v2/';
        } else {
            $this->base_url = 'https://api.myfatoorah.com/v2/';
        }

        $this->payment = $payment;

    }

    public function index(Request $request): View|Application|Factory|JsonResponse|\Illuminate\Contracts\Foundation\Application
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

        if (isset($this->api_key)) {
            $response = Http::withToken($this->api_key)->post($this->base_url . 'InitiateSession', [
                'CustomerIdentifier' => $request['payment_id'] . '_' . $payment_data->payer_id,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $country_code = $data['Data']['CountryCode'];
                $session_id = $data['Data']['SessionId'];
                return view('Gateways::payment.fatoorah', compact('country_code', 'session_id', 'payment_data'));
            }
        }

        return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
    }

    public function checkout(Request $request): JsonResponse
    {
        $body = [
            "SessionId" => $request->sessionId,
            "InvoiceValue" => round(session('data')['order_amount'], 3),
            "CustomerName" => session('data')['name'],
            "DisplayCurrencyIso" => "KWD",
            "CallBackUrl" => route('fatoorah.paymentstatus', ['payment_id' => $request['payment_id']]),
            "ErrorUrl" => route('payment-fail'),
            "Language" => "ar",
            "CustomerReference" => "noshipping-nosupplier"
        ];

        $response = Http::withToken($this->api_key)->post($this->base_url . 'ExecutePayment', $body);
        if ($response->successful()) {
            session()->put('transaction_reference', $response->json()['Data']['InvoiceId']);
            return response()->json($response->json()['Data']['PaymentURL'], $response->status());
        } else {
            return response()->json($response->json()['Message'], $response->status());
        }
    }

    public function check_payment(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $body = [
            "Key" => $request->query('paymentId'),
            "KeyType" => "PaymentId"
        ];
        $response = Http::withToken($this->api_key)->post($this->base_url . 'GetPaymentStatus', $body);
        if ($response->successful()) {
            if ($response->json()['Data']['InvoiceStatus'] == 'Paid') {
                $this->payment::where(['id' => $request['payment_id']])->update([
                    'payment_method' => 'fatoorah',
                    'is_paid' => 1,
                    'transaction_id' => $request['payment_id'],
                ]);
                $data = $this->payment::where(['id' => $request['payment_id']])->first();
                if (isset($data) && function_exists($data->success_hook)) {
                    call_user_func($data->success_hook, $data);
                }
                return $this->payment_response($data, 'success');
            }
        }
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }

}
