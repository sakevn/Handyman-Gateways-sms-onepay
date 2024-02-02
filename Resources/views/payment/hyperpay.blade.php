<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{translate('Hyperpay')}}</title>
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/hyperpay.css')}}">
</head>

<body>
<form action="{{ route('hyperpay.callback', ['payment_id' => $payment_id]) }}" class="paymentWidgets"
      data-brands="{{translate('VISA MASTER')}}">
</form>
@if ($config_mode == 'test')
    <script async src="https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId={{ $checkoutId }}"></script>
@else
    <script async src="https://eu-prod.oppwa.com/v1/paymentWidgets.js?checkoutId={{ $checkoutId }}"></script>
@endif

<script src="{{asset('Modules/Gateways/public/assets/modules/js/hyperpay.js')}}"></script>

</body>

</html>
