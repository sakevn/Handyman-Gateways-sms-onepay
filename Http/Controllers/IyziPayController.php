<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Processor;
use Modules\Gateways\Entities\PaymentRequest;

class IyziPayController extends Controller
{
    use Processor;

    private mixed $config_values;
    private $api_key;
    private $secret_key;
    private $base_url;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('iyzipay', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $this->api_key = $this->config_values->api_key;
            $this->secret_key = $this->config_values->secret_key;
            $this->base_url = $this->config_values->base_url;
        }

        $this->payment = $payment;
    }

    public function index(Request $request): View|\Illuminate\Foundation\Application|Factory|JsonResponse|Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment_id = $request->payment_id;
        return view('Gateways::payment.iyzipay', compact('payment_id'));
    }

    public function payment(Request $req): JsonResponse|RedirectResponse
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

        $options = new \Iyzipay\Options();
        $options->setApiKey($this->api_key);
        $options->setSecretKey($this->secret_key);
        $options->setBaseUrl($this->base_url);
        $callback = route('iyzipay.callback', ['payment_id' => $data->id]);

        $request = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
        $request->setLocale(\Iyzipay\Model\Locale::EN);
        $request->setConversationId($data->id);
        $request->setPrice($data->payment_amount);
        $request->setPaidPrice($data->payment_amount);
        $request->setCurrency(\Iyzipay\Model\Currency::TL);
        $request->setBasketId("B-" . $data->id);
        $request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
        $request->setCallbackUrl($callback);
        $request->setEnabledInstallments(array(2, 3, 6, 9));

        $buyer = new \Iyzipay\Model\Buyer();
        $buyer->setId($data->payer_id);
        $buyer->setName($payer->name);
        $buyer->setSurname($payer->name);
        $buyer->setGsmNumber($payer->phone);
        $buyer->setEmail($payer->email);
        $buyer->setIdentityNumber($data->id);
        $buyer->setRegistrationAddress('turkey');
        $buyer->setIp($req->ip());
        $buyer->setCity($req->city);
        $buyer->setCountry("Turkey");
        $buyer->setZipCode($req->zip);
        $request->setBuyer($buyer);
        $shippingAddress = new \Iyzipay\Model\Address();
        $shippingAddress->setContactName($payer->name);
        $shippingAddress->setCity($req->city);
        $shippingAddress->setCountry("Turkey");
        $shippingAddress->setAddress('turkey');
        $shippingAddress->setZipCode($req->zip);
        $request->setShippingAddress($shippingAddress);

        $billingAddress = new \Iyzipay\Model\Address();
        $billingAddress->setContactName($payer->name);
        $billingAddress->setCity($req->city);
        $billingAddress->setCountry("Turkey");
        $billingAddress->setAddress('turkey');
        $billingAddress->setZipCode($req->zip);
        $request->setBillingAddress($billingAddress);

        $basketItems = array();
        $firstBasketItem = new \Iyzipay\Model\BasketItem();
        $firstBasketItem->setId("BI-" . $data->id);
        $firstBasketItem->setName("6amMart");
        $firstBasketItem->setCategory1("6amMart");
        $firstBasketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
        $firstBasketItem->setPrice($data->payment_amount);
        $basketItems[0] = $firstBasketItem;
        $request->setBasketItems($basketItems);

        $checkoutFormInitialize = \Iyzipay\Model\CheckoutFormInitialize::create($request, $options);

        $url = $checkoutFormInitialize->getPaymentPageUrl();

        return redirect()->to($url);

    }

    public function callback(Request $req): \Illuminate\Foundation\Application|JsonResponse|Redirector|Application|RedirectResponse
    {
        $token = $req->token;
        $options = new \Iyzipay\Options();
        $options->setApiKey($this->api_key);
        $options->setSecretKey($this->secret_key);
        $options->setBaseUrl($this->base_url);
        $request = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
        $request->setLocale(\Iyzipay\Model\Locale::EN);
        $request->setConversationId($req->payment_id);
        $request->setToken($token);

        $checkoutForm = \Iyzipay\Model\CheckoutForm::retrieve($request, $options);
        $result = $checkoutForm->getRawResult();
        $response = json_decode($result, true);
        if ($response['status'] == 'success') {
            $this->payment::where(['id' => $req['payment_id']])->update([
                'payment_method' => 'iyzi_pay',
                'is_paid' => 1,
                'transaction_id' => $response['hostReference'],
            ]);

            $data = $this->payment::where(['id' => $req['payment_id']])->first();

            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }

            return $this->payment_response($data, 'success');
        }
        $payment_data = $this->payment::where(['id' => $req['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }
}
