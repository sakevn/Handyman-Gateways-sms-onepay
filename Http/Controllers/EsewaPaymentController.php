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

class EsewaPaymentController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $merchantCode;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('esewa', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->merchantCode = $this->config_values->merchantCode;
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

        $data = $this->payment::where(['id' => $req['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $config_val = $this->config_values;
        $config_mode = $this->config_mode;

        return view('Gateways::payment.esewa', compact('data', 'config_val', 'config_mode'));
    }

    public function verify(Request $request, $payment_id): Application|JsonResponse|int|Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {

        $url = "https://uat.esewa.com.np/epay/transrec";

        if ($request->q == 'su') {

            $order_id = $request->oid;
            $reference_id = $request->refId;
            $amount = $request->amt;

            $data = [
                'amt' => $amount,
                'rid' => $reference_id,
                'pid' => $order_id,
                'scd' => $this->merchantCode
            ];

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);
            curl_close($curl);

            if (strpos($response, 'Success') != false) {
                $this->payment::where(['id' => $payment_id])->update([
                    'payment_method' => 'esewa',
                    'is_paid' => 1,
                    'transaction_id' => $reference_id,
                ]);

                $payment_data = $this->payment::where(['id' => $payment_id])->first();

                if (isset($payment_data) && function_exists($payment_data->success_hook)) {
                    call_user_func($payment_data->success_hook, $payment_data);
                }

                return $this->payment_response($payment_data, 'success');
            }

        } elseif ($request->q == 'fu') {
            $payment_data = $this->payment::where(['id' => $payment_id])->first();
            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }
            return $this->payment_response($payment_data, 'fail');
        }

        return 0;
    }

}
