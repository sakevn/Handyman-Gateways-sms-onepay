<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Gateways\Traits\AddonActivationClass;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\Setting;

class PaymentConfigController extends Controller
{
    use Processor, AddonActivationClass;

    private $config_values;
    private $merchant_key;
    private $config_mode;
    private Setting $payment_setting;

    public function __construct(Setting $payment_setting)
    {
        $this->payment_setting = $payment_setting;
    }

    public function payment_config_get()
    {
        $response = $this->isActive();
        if(!$response['active']){
            Toastr::error(GATEWAYS_DEFAULT_400['message']);
            return redirect($response['route']);
        }

        $data_values = $this->payment_setting->whereIn('settings_type', ['payment_config'])->get();
        if (base64_decode(env('SOFTWARE_ID')) == '40224772') {
            return view('Gateways::payment-config.demandium-payment-config', compact('data_values'));
        } else {
            return view('Gateways::payment-config.payment-config', compact('data_values'));
        }
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function payment_config_set(Request $request): RedirectResponse
    {
        collect(['status'])->each(fn($item, $key) => $request[$item] = $request->has($item) ? (int)$request[$item] : 0);
        $validation = [
            'gateway' => 'required|in:ssl_commerz,sixcash,worldpay,payfast,swish,esewa,maxicash,hubtel,viva_wallet,tap,thawani,moncash,pvit,ccavenue,foloosi,iyzi_pay,xendit,fatoorah,hyper_pay,amazon_pay,paypal,stripe,razor_pay,senang_pay,paytabs,paystack,paymob_accept,paytm,flutterwave,liqpay,bkash,mercadopago,cash_after_service,digital_payment,momo,phonepe,cashfree,instamojo',
            'mode' => 'required|in:live,test'
        ];

        $additional_data = [];

        if ($request['gateway'] == 'ssl_commerz') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'store_id' => 'required',
                'store_password' => 'required'
            ];
        } elseif ($request['gateway'] == 'paypal') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'client_id' => 'required',
                'client_secret' => 'required'
            ];
        } elseif ($request['gateway'] == 'stripe') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
                'published_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'razor_pay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
                'api_secret' => 'required'
            ];
        } elseif ($request['gateway'] == 'senang_pay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'callback_url' => 'required',
                'secret_key' => 'required',
                'merchant_id' => 'required'
            ];
        } elseif ($request['gateway'] == 'paytabs') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'profile_id' => 'required',
                'server_key' => 'required',
                'base_url' => 'required'
            ];
        } elseif ($request['gateway'] == 'paystack') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'public_key' => 'required',
                'secret_key' => 'required',
                'merchant_email' => 'required'
            ];
        } elseif ($request['gateway'] == 'paymob_accept') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'callback_url' => 'required',
                'api_key' => 'required',
                'iframe_id' => 'required',
                'integration_id' => 'required',
                'hmac' => 'required'
            ];
        } elseif ($request['gateway'] == 'mercadopago') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'access_token' => 'required',
                'public_key' => 'required'
            ];
        } elseif ($request['gateway'] == 'liqpay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'private_key' => 'required',
                'public_key' => 'required'
            ];
        } elseif ($request['gateway'] == 'flutterwave') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'secret_key' => 'required',
                'public_key' => 'required',
                'hash' => 'required'
            ];
        } elseif ($request['gateway'] == 'paytm') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'merchant_key' => 'required',
                'merchant_id' => 'required',
                'merchant_website_link' => 'required'
            ];
        } elseif ($request['gateway'] == 'bkash') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'app_key' => 'required',
                'app_secret' => 'required',
                'username' => 'required',
                'password' => 'required',
            ];
        } elseif ($request['gateway'] == 'cash_after_service') {
            $additional_data = [
                'status' => 'required|in:1,0'
            ];
        } elseif ($request['gateway'] == 'digital_payment') {
            $additional_data = [
                'status' => 'required|in:1,0'
            ];
        } elseif ($request['gateway'] == 'momo') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
                'api_user' => 'required',
                'subscription_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'hyper_pay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'entity_id' => 'required',
                'access_code' => 'required',
            ];
        } elseif ($request['gateway'] == 'amazon_pay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'pass_phrase' => 'required',
                'access_code' => 'required',
                'merchant_identifier' => 'required',
            ];
        } elseif ($request['gateway'] == 'sixcash') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'public_key' => 'required',
                'secret_key' => 'required',
                'merchant_number' => 'required',
                'base_url' => 'required',
            ];
        } elseif ($request['gateway'] == 'worldpay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'OrgUnitId' => 'required',
                'jwt_issuer' => 'required',
                'mac' => 'required',
                'merchantCode' => 'required',
                'xml_password' => 'required',
            ];
        } elseif ($request['gateway'] == 'payfast') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'merchant_id' => 'required',
                'secured_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'swish') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'number' => 'required',
            ];
        } elseif ($request['gateway'] == 'esewa') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'merchantCode' => 'required',
            ];
        } elseif ($request['gateway'] == 'maxicash') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'merchantId' => 'required',
                'merchantPassword' => 'required',
            ];
        } elseif ($request['gateway'] == 'hubtel') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'account_number' => 'required',
                'api_id' => 'required',
                'api_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'viva_wallet') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'client_id' => 'required',
                'client_secret' => 'required',
                'source_code' => 'required',
            ];
        } elseif ($request['gateway'] == 'tap') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'secret_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'thawani') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'public_key' => 'required',
                'private_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'moncash') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'client_id' => 'required',
                'secret_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'pvit') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'mc_tel_merchant' => 'required',
                'access_token' => 'required',
                'mc_merchant_code' => 'required',
            ];
        } elseif ($request['gateway'] == 'ccavenue') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'merchant_id' => 'required',
                'working_key' => 'required',
                'access_code' => 'required',
            ];
        } elseif ($request['gateway'] == 'foloosi') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'merchant_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'iyzi_pay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
                'secret_key' => 'required',
                'base_url' => 'required',
            ];
        } elseif ($request['gateway'] == 'xendit') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'fatoorah') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'phonepe') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'merchant_id' => 'required',
                'salt_Key' => 'required',
                'salt_index' => 'required',
            ];
        } elseif ($request['gateway'] == 'cashfree') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'client_id' => 'required',
                'client_secret' => 'required',
            ];
        } elseif ($request['gateway'] == 'instamojo') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
            ];
        }

        $request->validate(array_merge($validation, $additional_data));

        $settings = $this->payment_setting->where('key_name', $request['gateway'])->where('settings_type', 'payment_config')->first();

        $additional_data_image = $settings['additional_data'] != null ? json_decode($settings['additional_data']) : null;

        if ($request->has('gateway_image')) {
            $gateway_image = $this->file_uploader('payment_modules/gateway_image/', 'png', $request['gateway_image'], $additional_data_image != null ? $additional_data_image->gateway_image : '');
        } else {
            $gateway_image = $additional_data_image != null ? $additional_data_image->gateway_image : '';
        }

        $payment_additional_data = [
            'gateway_title' => $request['gateway_title'],
            'gateway_image' => $gateway_image,
        ];

        $validator = Validator::make($request->all(), array_merge($validation, $additional_data));

        $this->payment_setting->updateOrCreate(['key_name' => $request['gateway'], 'settings_type' => 'payment_config'], [
            'key_name' => $request['gateway'],
            'live_values' => $validator->validate(),
            'test_values' => $validator->validate(),
            'settings_type' => 'payment_config',
            'mode' => $request['mode'],
            'is_active' => $request['status'],
            'additional_data' => json_encode($payment_additional_data),
        ]);

        Toastr::success(GATEWAYS_DEFAULT_UPDATE_200['message']);
        return back();
    }
}
