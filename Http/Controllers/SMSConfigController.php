<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\Setting;

class SMSConfigController extends Controller
{
    use Processor;

    private $config_values;
    private $merchant_key;
    private $config_mode;
    private Setting $setting;

    public function __construct(Setting $setting)
    {
        $this->setting = $setting;
    }


    public function sms_config_get(): Application|Factory|View|\Illuminate\Foundation\Application
    {
        $data_values = $this->setting->whereIn('settings_type', ['sms_config'])->get();
        if (base64_decode(env('SOFTWARE_ID')) == '40224772') {
            return view('Gateways::sms-config.demandium-sms-config', compact('data_values'));
        } else {
            return view('Gateways::sms-config.sms-config', compact('data_values'));
        }
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return RedirectResponse
     */
    public function sms_config_set(Request $request): RedirectResponse
    {
        $validation = [
            'gateway' => 'required|in:releans,twilio,nexmo,2factor,msg91,hubtel,paradox,signal_wire,019_sms,viatech,global_sms,akandit_sms,sms_to,alphanet_sms',
            'mode' => 'required|in:live,test'
        ];
        $additional_data = [];
        if ($request['gateway'] == 'releans') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
                'from' => 'required',
                'otp_template' => 'required'
            ];
        } elseif ($request['gateway'] == 'twilio') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'sid' => 'required',
                'messaging_service_sid' => 'required',
                'token' => 'required',
                'from' => 'required',
                'otp_template' => 'required'
            ];
        } elseif ($request['gateway'] == 'nexmo') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
                'api_secret' => 'required',
                'token' => 'required',
                'from' => 'required',
                'otp_template' => 'required'
            ];
        } elseif ($request['gateway'] == '2factor') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required'
            ];
        } elseif ($request['gateway'] == 'msg91') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'template_id' => 'required',
                'auth_key' => 'required',
            ];
        } elseif ($request['gateway'] == 'hubtel') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'sender_id' => 'required',
                'client_id' => 'required',
                'client_secret' => 'required',
                'otp_template' => 'required',
            ];
        } elseif ($request['gateway'] == 'paradox') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
                'sender_id' => 'required',
            ];
        } elseif ($request['gateway'] == 'signal_wire') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'project_id' => 'required',
                'token' => 'required',
                'space_url' => 'required',
                'from' => 'required',
                'otp_template' => 'required',
            ];
        } elseif ($request['gateway'] == '019_sms') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'password' => 'required',
                'username' => 'required',
                'username_for_token' => 'required',
                'sender' => 'required',
                'otp_template' => 'required',
            ];
        } elseif ($request['gateway'] == 'viatech') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_url' => 'required',
                'api_key' => 'required',
                'sender_id' => 'required',
                'otp_template' => 'required',
            ];
        } elseif ($request['gateway'] == 'global_sms') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'user_name' => 'required',
                'password' => 'required',
                'from' => 'required',
                'otp_template' => 'required',
            ];
        } elseif ($request['gateway'] == 'akandit_sms') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'username' => 'required',
                'password' => 'required',
                'otp_template' => 'required',
            ];
        } elseif ($request['gateway'] == 'sms_to') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
                'sender_id' => 'required',
                'otp_template' => 'required',
            ];
        } elseif ($request['gateway'] == 'alphanet_sms') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required',
                'otp_template' => 'required',
            ];
        }



        $validation = $request->validate(array_merge($validation, $additional_data));

        $this->setting->updateOrCreate(['key_name' => $request['gateway'], 'settings_type' => 'sms_config'], [
            'key_name' => $request['gateway'],
            'live_values' => $validation,
            'test_values' => $validation,
            'settings_type' => 'sms_config',
            'mode' => $request['mode'],
            'is_active' => $request['status'],
        ]);

        if ($request['status'] == 1) {
            foreach (['releans', 'twilio', 'nexmo', '2factor', 'msg91', 'hubtel', 'paradox', 'signal_wire', '019_sms', 'viatech', 'global_sms', 'akandit_sms', 'sms_to', 'alphanet_sms'] as $gateway) {
                if ($request['gateway'] != $gateway) {
                    $keep = $this->setting->where(['key_name' => $gateway, 'settings_type' => 'sms_config'])->first();
                    if (isset($keep)) {
                        $hold = $keep->live_values;
                        $hold['status'] = 0;
                        $this->setting->where(['key_name' => $gateway, 'settings_type' => 'sms_config'])->update([
                            'live_values' => $hold,
                            'test_values' => $hold,
                            'is_active' => 0,
                        ]);
                    }
                }
            }
        }

        Toastr::success(GATEWAYS_DEFAULT_UPDATE_200['message']);
        return back();
    }
}
