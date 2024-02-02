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

class PayFastController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $merchant_id;
    private $secured_key;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('payfast', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->merchant_id = $this->config_values->merchant_id;
            $this->secured_key = $this->config_values->secured_key;
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

        $payment_data = $this->payment::where(['id' => $req['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($payment_data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($payment_data['payer_information']);

        $merchant_id = $this->merchant_id;
        $secured_key = $this->secured_key;
        $trans_amount = $payment_data->payment_amount;
        $basket_id = $payment_data->attribute_id;
        $tokenApiUrl = 'https://ipguat.apps.net.pk/Ecommerce/api/Transaction/GetAccessToken';

        $urlPostParams = sprintf(
            'MERCHANT_ID=%s&SECURED_KEY=%s&TXNAMT=%s&BASKET_ID=%s',
            $merchant_id,
            $secured_key,
            $trans_amount,
            $basket_id
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $urlPostParams);
        curl_setopt($ch, CURLOPT_USERAGENT, 'CURL/PHP PayFast Example');
        $response = curl_exec($ch);
        curl_close($ch);
        $payload = json_decode($response, true);

        $token = isset($payload['ACCESS_TOKEN']) ? $payload['ACCESS_TOKEN'] : '';
        $requestParams = array(
            'MERCHANT_ID' => $merchant_id,
            'Merchant_Name' => 'Example',
            'TOKEN' => $token,
            'PROCCODE' => 00,
            'TXNAMT' => $payment_data->payment_amount,
            'CUSTOMER_MOBILE_NO' => $payer->phone,
            'CUSTOMER_EMAIL_ADDRESS' => $payer->email,
            'SIGNATURE' => bin2hex(random_bytes(6)) . '-' . $payment_data->attribute_id,
            'VERSION' => 'MERCHANT-CART-0.1',
            'TXNDESC' => 'Payfast Payment',
            'SUCCESS_URL' => route('payfast.callback', ['payment_id' => $payment_data->id]),
            'FAILURE_URL' => route('payfast.callback', ['payment_id' => $payment_data->id]),
            'BASKET_ID' => $payment_data->attribute_id,
            'ORDER_DATE' => $payment_data->created_at,
            'CHECKOUT_URL' => route('payfast.callback', ['payment_id' => $payment_data->id]),

        );

        $redirectUrl = 'https://ipguat.apps.net.pk/Ecommerce/api/Transaction/PostTransaction';
        return view('Gateways::payment.payfast', compact(['requestParams', 'redirectUrl']));

    }

    public function callback(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        if ($request->err_code == "000") {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'payfast',
                'is_paid' => 1,
                'transaction_id' => $request->transaction_id,
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
