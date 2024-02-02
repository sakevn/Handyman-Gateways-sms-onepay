<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;

/*

*/

use Modules\Gateways\Http\Controllers\CashFreePaymentController;
use Modules\Gateways\Http\Controllers\InstamojoPaymentController;
use Modules\Gateways\Http\Controllers\PhonepeController;
use Modules\Gateways\Http\Controllers\SslCommerzPaymentController;
use Modules\Gateways\Http\Controllers\StripePaymentController;
use Modules\Gateways\Http\Controllers\PaymobController;
use Modules\Gateways\Http\Controllers\FlutterwaveV3Controller;
use Modules\Gateways\Http\Controllers\PaytmController;
use Modules\Gateways\Http\Controllers\PaypalPaymentController;
use Modules\Gateways\Http\Controllers\PaytabsController;
use Modules\Gateways\Http\Controllers\LiqPayController;
use Modules\Gateways\Http\Controllers\RazorPayController;
use Modules\Gateways\Http\Controllers\SenangPayController;
use Modules\Gateways\Http\Controllers\MercadoPagoController;
use Modules\Gateways\Http\Controllers\BkashPaymentController;
use Modules\Gateways\Http\Controllers\PaystackController;
use Modules\Gateways\Http\Controllers\FatoorahPaymentController;
use Modules\Gateways\Http\Controllers\TapPaymentController;
use Modules\Gateways\Http\Controllers\XenditPaymentController;
use Modules\Gateways\Http\Controllers\AmazonPaymentController;
use Modules\Gateways\Http\Controllers\IyziPayController;
use Modules\Gateways\Http\Controllers\HyperPayController;
use Modules\Gateways\Http\Controllers\FoloosiPaymentController;
use Modules\Gateways\Http\Controllers\CCavenueController;
use Modules\Gateways\Http\Controllers\PvitController;
use Modules\Gateways\Http\Controllers\MoncashController;
use Modules\Gateways\Http\Controllers\ThawaniPaymentController;
use Modules\Gateways\Http\Controllers\VivaWalletController;
use Modules\Gateways\Http\Controllers\HubtelPaymentController;
use Modules\Gateways\Http\Controllers\MaxiCashController;
use Modules\Gateways\Http\Controllers\EsewaPaymentController;
use Modules\Gateways\Http\Controllers\SwishPaymentController;
use Modules\Gateways\Http\Controllers\MomoPayController;
use Modules\Gateways\Http\Controllers\PayFastController;
use Modules\Gateways\Http\Controllers\WorldPayController;
use Modules\Gateways\Http\Controllers\SixcashPaymentController;
use Modules\Gateways\Http\Controllers\PaymentConfigController;

$is_published = 0;
try {
    $full_data = include('Modules/Gateways/Addon/info.php');
    $is_published = $full_data['is_published'] == 1 ? 1 : 0;
} catch (\Exception $exception) {
}

if ($is_published) {
    Route::group(['prefix' => 'payment'], function () {

        //SSLCOMMERZ
        Route::group(['prefix' => 'sslcommerz', 'as' => 'sslcommerz.'], function () {
            Route::get('pay', [SslCommerzPaymentController::class, 'index'])->name('pay');
            Route::post('success', [SslCommerzPaymentController::class, 'success'])
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::post('failed', [SslCommerzPaymentController::class, 'failed'])
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::post('canceled', [SslCommerzPaymentController::class, 'canceled'])
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //STRIPE
        Route::group(['prefix' => 'stripe', 'as' => 'stripe.'], function () {
            Route::get('pay', [StripePaymentController::class, 'index'])->name('pay');
            Route::get('token', [StripePaymentController::class, 'payment_process_3d'])->name('token');
            Route::get('success', [StripePaymentController::class, 'success'])->name('success');
        });

        //RAZOR-PAY
        Route::group(['prefix' => 'razor-pay', 'as' => 'razor-pay.'], function () {
            Route::get('pay', [RazorPayController::class, 'index']);
            Route::post('payment', [RazorPayController::class, 'payment'])->name('payment')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //PAYPAL
        Route::group(['prefix' => 'paypal', 'as' => 'paypal.'], function () {
            Route::get('pay', [PaypalPaymentController::class, 'payment']);
            Route::any('success', [PaypalPaymentController::class, 'success'])->name('success')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);;
            Route::any('cancel', [PaypalPaymentController::class, 'cancel'])->name('cancel')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //SENANG-PAY
        Route::group(['prefix' => 'senang-pay', 'as' => 'senang-pay.'], function () {
            Route::get('pay', [SenangPayController::class, 'index']);
            Route::any('callback', [SenangPayController::class, 'return_senang_pay'])
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //PAYTM
        Route::group(['prefix' => 'paytm', 'as' => 'paytm.'], function () {
            Route::get('pay', [PaytmController::class, 'payment']);
            Route::any('response', [PaytmController::class, 'callback'])->name('response')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //FLUTTERWAVE
        Route::group(['prefix' => 'flutterwave-v3', 'as' => 'flutterwave-v3.'], function () {
            Route::get('pay', [FlutterwaveV3Controller::class, 'initialize'])->name('pay');
            Route::get('callback', [FlutterwaveV3Controller::class, 'callback'])->name('callback');
        });

        //PAYSTACK
        Route::group(['prefix' => 'paystack', 'as' => 'paystack.'], function () {
            Route::get('pay', [PaystackController::class, 'index'])->name('pay');
            Route::post('payment', [PaystackController::class, 'redirectToGateway'])->name('payment');
            Route::get('callback', [PaystackController::class, 'handleGatewayCallback'])->name('callback');
        });

        //BKASH

        Route::group(['prefix' => 'bkash', 'as' => 'bkash.'], function () {
            Route::get('make-payment', [BkashPaymentController::class, 'make_tokenize_payment'])->name('make-payment');
            Route::any('callback', [BkashPaymentController::class, 'callback'])->name('callback')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //Liqpay
        Route::group(['prefix' => 'liqpay', 'as' => 'liqpay.'], function () {
            Route::get('payment', [LiqPayController::class, 'payment'])->name('payment');
            Route::any('callback', [LiqPayController::class, 'callback'])->name('callback')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //MERCADOPAGO
        Route::group(['prefix' => 'mercadopago', 'as' => 'mercadopago.'], function () {
            Route::get('pay', [MercadoPagoController::class, 'index'])->name('index');
            Route::post('make-payment', [MercadoPagoController::class, 'make_payment'])->name('make_payment');
        });

        //PAYMOB
        Route::group(['prefix' => 'paymob', 'as' => 'paymob.'], function () {
            Route::any('pay', [PaymobController::class, 'credit'])->name('pay');
            Route::any('callback', [PaymobController::class, 'callback'])->name('callback')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //PAYTABS
        Route::group(['prefix' => 'paytabs', 'as' => 'paytabs.'], function () {
            Route::any('pay', [PaytabsController::class, 'payment'])->name('pay');
            Route::any('callback', [PaytabsController::class, 'callback'])->name('callback')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('response', [PaytabsController::class, 'response'])->name('response')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //Fatoora
        Route::group(['prefix' => 'fatoorah', 'as' => 'fatoorah.'], function () {
            Route::any('pay/', [FatoorahPaymentController::class, 'index'])->name('index');
            Route::post('checkout', [FatoorahPaymentController::class, 'checkout'])->name('checkout');
            Route::get('paymentstatus', [FatoorahPaymentController::class, 'check_payment'])->name('paymentstatus');
        });

        //xendit
        Route::group(['prefix' => 'xendit', 'as' => 'xendit.'], function () {
            Route::get('pay', [XenditPaymentController::class, 'payment'])->name('pay');
            Route::any('callback', [XenditPaymentController::class, 'callBack'])->name('callback')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //amazon
        Route::group(['prefix' => 'amazon', 'as' => 'amazon.'], function () {
            Route::get('pay', [AmazonPaymentController::class, 'payment'])->name('pay');
            Route::any('callback', [AmazonPaymentController::class, 'callBackResponse'])->name('callBackResponse')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('callbackstatus', [AmazonPaymentController::class, 'callback'])->name('callBack')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //iyzipay
        Route::group(['prefix' => 'iyzipay', 'as' => 'iyzipay.'], function () {
            Route::get('pay', [IyziPayController::class, 'index'])->name('index');
            Route::get('payment', [IyziPayController::class, 'payment'])->name('payment');
            Route::any('callback', [IyziPayController::class, 'callback'])->name('callback')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //Hyperpay
        Route::group(['prefix' => 'hyperpay', 'as' => 'hyperpay.'], function () {
            Route::get('pay', [HyperPayController::class, 'payment'])->name('pay');
            Route::any('callback', [HyperPayController::class, 'callback'])->name('callback')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //foloosi
        Route::group(['prefix' => 'foloosi', 'as' => 'foloosi.'], function () {
            Route::any('pay', [FoloosiPaymentController::class, 'payment'])->name('payment')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('callback', [FoloosiPaymentController::class, 'callback'])->name('callback')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //CCavenue
        Route::group(['prefix' => 'ccavenue', 'as' => 'ccavenue.'], function () {
            Route::any('pay', [CCavenueController::class, 'payment'])->name('payment-request');
            Route::any('payment-response', [CCavenueController::class, 'payment_response_process'])->name('payment-response')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('payment-cancel', [CCavenueController::class, 'payment_cancel'])->name('payment-cancel')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //Pvit
        Route::group(['prefix' => 'pvit', 'as' => 'pvit.'], function () {
            Route::any('pay', [PvitController::class, 'payment'])->name('pay')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('callback', [PvitController::class, 'callBack'])->name('callBack')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //moncash
        Route::group(['prefix' => 'moncash', 'as' => 'moncash.'], function () {
            Route::get('pay', [MoncashController::class, 'payment'])->name('payment');
            Route::any('callback', [MoncashController::class, 'callback'])->name('callback')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //thawani
        Route::group(['prefix' => 'thawani', 'as' => 'thawani.'], function () {
            Route::get('pay', [ThawaniPaymentController::class, 'checkout'])->name('payment');
            Route::get('success', [ThawaniPaymentController::class, 'success'])->name('success');
            Route::get('cancel', [ThawaniPaymentController::class, 'cancel'])->name('cancel');
        });

        //tap
        Route::group(['prefix' => 'tap', 'as' => 'tap.'], function () {
            Route::get('pay', [TapPaymentController::class, 'payment'])->name('payment');
            Route::any('callback', [TapPaymentController::class, 'callback'])->name('callback')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //viva wallet
        Route::group(['prefix' => 'viva', 'as' => 'viva.'], function () {
            Route::get('pay', [VivaWalletController::class, 'payment'])->name('payment');
            Route::get('success-callback', [VivaWalletController::class, 'success'])->name('success');
            Route::get('fail', [VivaWalletController::class, 'fail'])->name('fail');
        });

        // Hubtel Payment
        Route::group(['prefix' => 'hubtel', 'as' => 'hubtel.'], function () {
            Route::any('pay', [HubtelPaymentController::class, 'payment'])->name('payments');
            Route::any('callback', [HubtelPaymentController::class, 'callback'])->name('callback')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::get('success', [HubtelPaymentController::class, 'success'])->name('success');
            Route::get('cancel', [HubtelPaymentController::class, 'cancel'])->name('cancel');
        });

        // Maxicash Payment
        Route::group(['prefix' => 'maxicash', 'as' => 'maxicash.'], function () {
            Route::get('index', [MaxiCashController::class, 'index'])->name('index');
            Route::get('pay', [MaxiCashController::class, 'payment'])->name('payment');
            Route::any('callback/{payment_id}/{status}', [MaxiCashController::class, 'callback'])->name('callback')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //Esewa Payment Gateway
        Route::group(['prefix' => 'esewa', 'as' => 'esewa.'], function () {
            Route::get('pay', [EsewaPaymentController::class, 'payment'])->name('payment');
            Route::get('verify/{payment_id}', [EsewaPaymentController::class, 'verify'])->name('verify');
        });

        // Swish Payment Gateway
        Route::group(['prefix' => 'swish', 'as' => 'swish.'], function () {
            Route::any('pay', [SwishPaymentController::class, 'index'])->name('payment');
            Route::post('make-payment', [SwishPaymentController::class, 'makePayment'])->name('make-payment')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::post('callback', [SwishPaymentController::class, 'callback'])->name('callback')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::post('m-callback', [SwishPaymentController::class, 'swish_m_callback'])->name('m-callback')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::get('check-payment', [SwishPaymentController::class, 'check_payment'])->name('check-payment');
        });

        //MTN MOMO
        Route::group(['prefix' => 'momo', 'as' => 'momo.'], function () {
            Route::any('callback', [MomoPayController::class, 'callback'])->name('callback')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('make-payment', [MomoPayController::class, 'makePayment'])->name('make-payment');
            Route::any('pay', [MomoPayController::class, 'payment'])->name('payment');
        });

        //Pay Fast
        Route::group(['prefix' => 'payfast', 'as' => 'payfast.'], function () {
            Route::get('pay', [PayFastController::class, 'payment'])->name('payment');
            Route::any('callback', [PayFastController::class, 'callback'])->name('callback')
            ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //World Pay
        Route::group(['prefix' => 'worldpay', 'as' => 'worldpay.'], function () {
            Route::get('pay', [WorldPayController::class, 'index'])->name('pay');
            Route::post('payment', [WorldPayController::class, 'payment'])->name('payment');
            Route::get('jwt', [WorldPayController::class, 'generate_jwt']);
        });

        //Six Cash
        Route::group(['prefix' => 'sixcash', 'as' => 'sixcash.'], function () {
            Route::any('pay', [SixcashPaymentController::class, 'payment'])->name('pay');
            Route::any('callback', [SixcashPaymentController::class, 'callback'])->name('payment');
        });

        //PHONEPE
        Route::group(['prefix' => 'phonepe', 'as' => 'phonepe.'], function () {
            Route::any('pay', [PhonepeController::class, 'payment'])->name('pay');
            Route::any('callback', [PhonepeController::class, 'callback'])->name('callback');
            Route::any('redirect', [PhonepeController::class, 'redirect'])->name('redirect')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //Cash Free
        Route::group(['prefix' => 'cashfree', 'as' => 'cashfree.'], function () {
            Route::any('pay', [CashFreePaymentController::class, 'payment'])->name('pay');
            Route::any('callback', [CashFreePaymentController::class, 'callback'])->name('payment')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //Instamojo
        Route::group(['prefix' => 'instamojo', 'as' => 'instamojo.'], function () {
            Route::any('pay', [InstamojoPaymentController::class, 'payment'])->name('pay');
            Route::any('callback', [InstamojoPaymentController::class, 'callback'])->name('payment')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

    });
}

Route::group(['prefix' => 'admin/payment'], function () {
    Route::group(['prefix' => 'configuration', 'as' => 'configuration.', 'middleware' => ['admin']], function () {
        Route::get('addon-payment-get', [PaymentConfigController::class, 'payment_config_get'])->name('addon-payment-get');
        Route::put('addon-payment-set', [PaymentConfigController::class, 'payment_config_set'])->name('addon-payment-set');
    });
});

Route::group(['prefix' => 'admin/sms'], function () {
    Route::group(['prefix' => 'configuration', 'as' => 'configuration.', 'middleware' => ['admin']], function () {
        Route::get('addon-sms-get', 'SMSConfigController@sms_config_get')->name('addon-sms-get');
        Route::put('addon-sms-set', 'SMSConfigController@sms_config_set')->name('addon-sms-set');
    });
});

