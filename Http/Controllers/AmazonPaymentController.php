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

class AmazonPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $access_code;
    private $passphrase;
    private $merchant_identifier;
    private string $url;
    private string $redirect_url;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('amazon_pay', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->access_code = $this->config_values->access_code;
            $this->passphrase = $this->config_values->pass_phrase;
            $this->merchant_identifier = $this->config_values->merchant_identifier;
            $this->redirect_url = ($config->mode == 'live') ? 'https://checkout.payfort.com/FortAPI/paymentPage' : 'https://sbcheckout.payfort.com/FortAPI/paymentPage';
            $this->url = ($config->mode == 'live') ? 'https://paymentservices.payfort.com/FortAPI/paymentApi' : 'https://sbpaymentservices.payfort.com/FortAPI/paymentApi';
        }

        $this->payment = $payment;
    }

    public function payment(Request $request): View|Application|Factory|JsonResponse|\Illuminate\Contracts\Foundation\Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($data['payer_information']);

        $shaString = '';
        $merchant_reference = $data->id;

        $this->payment::where(['id' => $request['payment_id']])->update([
            'transaction_id' => $merchant_reference,
        ]);

        $access_code = $this->access_code;
        $passphrase = $this->passphrase;
        $merchant_identifier = $this->merchant_identifier;
        $requestParams = array(
            'command' => 'PURCHASE',
            'access_code' => $access_code,
            'merchant_identifier' => $merchant_identifier,
            'merchant_reference' => $merchant_reference,
            'amount' => $data->payment_amount * 1000,
            'currency' => 'OMR',
            'language' => 'en',
            'customer_email' => $payer->email,
            'order_description' => $data->id,
            'return_url' => route('amazon.callBackResponse', ['payment_id' => $request['payment_id']])
        );

        ksort($requestParams);
        foreach ($requestParams as $key => $value) {
            $shaString .= "$key=$value";
        }
        $shaString = $passphrase . $shaString . $passphrase;

        $signature = hash("sha256", $shaString);
        $requestParams['signature'] = $signature;

        $redirectUrl = $this->redirect_url;
        return view('Gateways::payment.amazon-payment', compact(['requestParams', 'redirectUrl']));
    }

    public function callBackResponse(Request $request): Application|JsonResponse|Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        if (in_array($request->status, ['02', '04', '14', '44'])) {

            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'amazon_pay',
                'is_paid' => 1,
                'transaction_id' => $request->merchant_reference ?? null,
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
