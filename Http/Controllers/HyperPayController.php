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

class HyperPayController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $entity_id;
    private $access_code;
    private string $config_mode;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('hyper_pay', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->entity_id = $this->config_values->entity_id;
            $this->access_code = $this->config_values->access_code;
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

        $payer = json_decode($data['payer_information']);

        $url = $this->config_mode == 'test' ? "https://eu-test.oppwa.com/v1/checkouts" : "https://eu-prod.oppwa.com/v1/checkouts";
        $abc = "entityId={$this->entity_id}" .
            "&amount={$data->payment_amount}" .
            "&currency=SAR" .
            "&merchantInvoiceId={$data->id}" .
            "&paymentType=DB" .
            "&customer.email={$payer->email}" .
            "&customer.givenName={$payer->name}" .
            "&customer.phone={$payer->phone}" .
            "&merchantTransactionId=mt-{$data->id}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:Bearer ' . $this->access_code
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $abc);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !($this->config_mode == 'test')); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch)) {
            return response()->json(['message' => curl_error($ch)]);
        }
        curl_close($ch);
        $result = json_decode($responseData);
        $checkoutId = $result->id;
        $config_mode = $this->config_mode;
        $payment_id = $data->id;
        return view('Gateways::payment.hyperpay', compact(['checkoutId', 'config_mode', 'payment_id']));
    }

    public function callback(Request $request): Application|JsonResponse|Redirector|string|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $id = $request->id;
        $url = $this->config_mode == 'test' ? "https://eu-test.oppwa.com/v1/checkouts/{$id}/payment" : "https://eu-prod.oppwa.com/v1/checkouts/{$id}/payment";
        $url .= "?entityId={$this->entity_id}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:Bearer ' . $this->access_code
        ));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !($this->config_mode == 'test')); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        $response = json_decode($responseData, true);

        if ($this->config_mode == 'test' ? $response['result']['code'] == '000.100.110' : $response['result']['code'] == '000.000.000') {
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'hyper_pay',
                'is_paid' => 1,
                'transaction_id' => $response['merchantInvoiceId'],
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
