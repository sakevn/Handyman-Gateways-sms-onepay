<html lang="en">
<head>
    <title>
        {{translate('PayFast Payment')}}
    </title>
</head>
<body>
<form action="{{ $redirectUrl }}" method="post" id='PayFast_payment_form' name="from1">
    @foreach ($requestParams as $a => $b)
        <input type="hidden" name="{{ $a }}" value="{{ $b }}">
    @endforeach
</form>
<script src="{{asset('Modules/Gateways/public/assets/modules/js/pay-fast.js')}}"></script>
</body>
</html>
