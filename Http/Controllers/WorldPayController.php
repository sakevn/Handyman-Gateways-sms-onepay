<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;

class WorldPayController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $OrgUnitId;
    private $jwt_issuer;
    private $mac;
    private $merchantCode;
    private $xml_password;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('worldpay', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->OrgUnitId = $this->config_values->OrgUnitId;
            $this->jwt_issuer = $this->config_values->jwt_issuer;
            $this->mac = $this->config_values->mac;
            $this->merchantCode = $this->config_values->merchantCode;
            $this->xml_password = $this->config_values->xml_password;
            $this->config_mode = ($config->mode == 'test') ? 'test' : 'live';
        }

        $this->payment = $payment;
    }

    public function index(Request $request): View|Application|Factory|JsonResponse|\Illuminate\Contracts\Foundation\Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment_data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($payment_data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $jwt = $this->generate_jwt();

        return view('Gateways::payment.worldpay-payment', compact('payment_data', 'jwt'));
    }

    public function payment(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {

        $request->validate([
            'card_no' => 'required',
            'expdate' => 'required',
            'Name' => 'required',
            'cvv' => 'required',
            'payment_id' => 'required|uuid',
        ]);

        $payment_data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($payment_data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($payment_data['payer_information']);

        $description = 'description';
        $address = $payer->address;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE paymentService PUBLIC "-//WorldPay//DTD WorldPay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">
        <paymentService version="1.4" merchantCode="' . $this->merchantCode . '">
            <submit>
                <order orderCode="' . $payment_data->attribute_id . '">
                    <description>' . $description . '</description>
                    <amount value="' . ($payment_data->payment_amount * 100) . '" currencyCode="' . $payment_data->currency_code . '" exponent="2"/>
                    <paymentDetails>
                        <CARD-SSL>
                            <cardNumber>' . str_replace("-", "", $request->card_no) . '</cardNumber>
                            <expiryDate>
                                <date month="' . explode("/", $request->expdate)[0] . '" year="20' . explode("/", $request->expdate)[1] . '"/>
                            </expiryDate>
                            <cardHolderName>' . $request->Name . '</cardHolderName>
                            <cvc>' . $request->cvv . '</cvc>
                            <cardAddress>
                                <address>
                                    <street>' . (isset($address['road']) ? $address['road'] : '') . '</street>
                                    <postalCode></postalCode>
                                    <countryCode>' . $payment_data->currency_code . '</countryCode>
                                </address>
                            </cardAddress>
                        </CARD-SSL>
                        <session shopperIPAddress="' . $_SERVER['SERVER_ADDR'] . '" id="' . $request->session_id . '" />
                    </paymentDetails>
                    <shopper>
                        <shopperEmailAddress>' . $payer->email . '</shopperEmailAddress>
                        <browser>
                            <acceptHeader>' . $_SERVER['HTTP_ACCEPT'] . '</acceptHeader>
                            <userAgentHeader>' . $_SERVER['HTTP_USER_AGENT'] . '</userAgentHeader>
                        </browser>
                    </shopper>
                </order>
            </submit>
        </paymentService>';

        $curl = curl_init();

        $url = 'secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp';
        if ($this->config_mode == 'live') {
            $url = 'secure.worldpay.com/jsp/merchant/xml/paymentService.jsp';
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$this->merchantCode}:{$this->xml_password}@{$url}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
                "Content-Type: application/xml",
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode(json_encode(new SimpleXMLElement($response)), true);

        if (isset($data['reply']['orderStatus']['payment']['lastEvent']) && $data['reply']['orderStatus']['payment']['lastEvent'] == 'AUTHORISED') {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'worldpay',
                'is_paid' => 1,
                'transaction_id' => \Carbon\Carbon::now()->toDateString(),
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

    public function generate_jwt(): string
    {
        $header = json_encode([
            "typ" => "JWT",
            "alg" => "HS256"
        ]);
        $body = json_encode([
            "jti" => Str::uuid(),
            "iat" => time(),
            "iss" => $this->jwt_issuer,
            "exp" => time() + 7140,
            "OrgUnitId" => $this->OrgUnitId
        ]);

        $signature = hash_hmac('sha256', $this->base64UrlEncode($header) . "." . $this->base64UrlEncode($body), $this->mac, true);
        return $this->base64UrlEncode($header) . "." . $this->base64UrlEncode($body) . "." . $this->base64UrlEncode($signature);

    }

    private function base64UrlEncode(string $data): string
    {
        $base64Url = strtr(base64_encode($data), '+/', '-_');

        return rtrim($base64Url, '=');
    }
}
