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

class SwishPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $number;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('swish', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->number = $this->config_values->number;
            $this->config_mode = ($config->mode == 'test') ? 'test' : 'live';
        }

        $this->payment = $payment;
    }

    public function index(Request $req)
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

        return view('Gateways::payment.swish-payment', compact('payment_data'));
    }

    public function makePayment(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'number' => 'required',
            'payment_link_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment_data = $this->payment::where(['id' => $request['payment_link_id']])->where(['is_paid' => 0])->first();
        if (!isset($payment_data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $headers = [];

        $payer_number = $request->number;
        $callback = $this->config_mode == "test" ? "https://stack-admin.6am.one/payment/swish/callback" : route("swish.callback");
        $SERVER_URL = $this->config_mode == "test" ? 'https://mss.cpc.getswish.net/swish-cpcapi/api/v1/paymentrequests' : 'https://cpc.getswish.net/swish-cpcapi/api/v1/paymentrequests/';

        $SSL_CERT = $this->config_mode == 'test' ? '/test/Swish_Merchant_TestCertificate_1234679304.pem' : '/live/swish_certificate_202210271434.pem';
        $CA_INFO = $this->config_mode == 'test' ? '/test/Swish_TLS_RootCA.pem' : '/live/MySwishCSR.csr';
        $SSL_KEY = $this->config_mode == 'test' ? '/test/Swish_Merchant_TestCertificate_1234679304.key' : '/live/MySwishKey.key';

        $ch = curl_init($SERVER_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '2');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '1');
        if ($this->config_mode == 'test') {
            curl_setopt($ch, CURLOPT_CAINFO, getcwd() . '/Modules/Gateways/public/modules/certificates' . $CA_INFO);
        }

        curl_setopt($ch, CURLOPT_SSLCERT, getcwd() . '/Modules/Gateways/public/modules/certificates' . $SSL_CERT);
        curl_setopt($ch, CURLOPT_SSLKEY, getcwd() . '/Modules/Gateways/public/modules/certificates' . $SSL_KEY);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) {
                    return $len;
                }
                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );

        $data = array("payeePaymentReference" => $request->order_id, "callbackUrl" => $callback, "payerAlias" => $payer_number, "payeeAlias" => $this->number, "amount" => (string)$payment_data->payment_amount, "currency" => "SEK", "message" => "");
        $data_string = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );

        if (!$response = curl_exec($ch)) {
            trigger_error(curl_error($ch));
        }
        curl_close($ch);

        if (isset($headers['location'][0])) {

            $parts = explode('/', $headers['location'][0]);
            $response_id = end($parts);

            return response()->json([
                'status' => 200,
                'id' => $response_id,
            ]);
        }
    }

    public function callback(Request $request): Application|bool|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $swish = $request->getContent();
        $data = json_decode($swish, true);
        if (!isset($data['payeePaymentReference'])) {
            return false;
        }
        if ($data['status'] == 'PAID') {
            $this->payment::where(['attribute_id' => $data['payeePaymentReference']])->update([
                'payment_method' => 'swish',
                'is_paid' => 1,
                'transaction_id' => $data['id'],
            ]);

            $payment_data = $this->payment::where(['attribute_id' => $data['payeePaymentReference']])->first();

            if (isset($payment_data) && function_exists($payment_data->success_hook)) {
                call_user_func($payment_data->success_hook, $payment_data);
            }

            return $this->payment_response($payment_data, 'success');
        }
        $payment_data = $this->payment::where(['attribute_id' => $data['payeePaymentReference']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }

    public function check_payment(Request $request): JsonResponse
    {
        if (!$payment = $this->payment::where('id', $request->payment_link_id)->first()) {
            return response()->json([
                'response' => 'failed',
            ]);
        }

        if ($payment->is_paid == 1) {
            return response()->json([
                'response' => 'success',
            ]);
        } else {
            return response()->json([
                'response' => 'failed',
            ]);
        }
    }

    public function swish_m_callback(Request $request): Application|bool|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $swish = $request->getContent();
        $data = json_decode($swish, true);
        if (!isset($data['message'])) {
            return false;
        }
        if (!$this->payment::where(['attribute_id' => $data['message']])->first()) return false;

        if ($data['status'] == 'PAID') {
            $this->payment::where(['attribute_id' => $data['message']])->update([
                'payment_method' => 'swish',
                'is_paid' => 1,
                'transaction_id' => $data['id'],
            ]);

            $payment_data = $this->payment::where(['attribute_id' => $data['message']])->first();

            if (isset($payment_data) && function_exists($payment_data->success_hook)) {
                call_user_func($payment_data->success_hook, $payment_data);
            }

            return $this->payment_response($payment_data, 'success');
        } else {
            $payment_data = $this->payment::where(['attribute_id' => $data['message']])->first();
            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }
            return $this->payment_response($payment_data, 'fail');
        }

    }
}
