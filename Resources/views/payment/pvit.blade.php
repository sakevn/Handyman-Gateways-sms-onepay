<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{translate('Pvit')}}</title>
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/pvit.css')}}">
</head>

<body>
<div class="card-body">
    <form action="https://mypvit.com/pvit-secure-full-api.kk" method="POST" id="payeForm">
        <input type="hidden" name="tel_marchand" value="{{ $config_val->mc_tel_merchant }}">
        <input type="hidden" name="montant" value="{{ $payment_data->payment_amount }}">
        <input type="hidden" name="ref" value="{{ $payment_data->id }}">
        <input type="hidden" name="service" value="WEB">
        <input type="hidden" name="operateur" value="MC">
        <input type="hidden" name="redirect" value="{{ route('pvit.callBack', ['payment_id' => $payment_data->id]) }}">
        <input type="hidden" name="token" value="{{ $config_val->access_token }}">
        <input type="hidden" name="agent" value="{{ $config_val->mc_merchant_code }}">
        <button class="btn btn-block btn-primary btn-lg click-if-alone" type="submit">
            <strong> {{translate('MobiCash')}}</strong>
        </button>
    </form>
</div>
<div class="card-body">
    <form action="https://mypvit.com/pvit-secure-full-api.kk" method="POST" id="payeForm">
        <input type="hidden" name="tel_marchand" value="{{ $config_val->mc_tel_merchant }}">
        <input type="hidden" name="montant" value="{{ $payment_data->payment_amount }}">
        <input type="hidden" name="ref" value="{{ $payment_data->id }}">
        <input type="hidden" name="service" value="WEB">
        <input type="hidden" name="operateur" value="AM">
        <input type="hidden" name="redirect" value="{{ route('pvit.callBack', ['payment_id' => $payment_data->id]) }}">
        <input type="hidden" name="token" value="{{ $config_val->access_token }}">
        <input type="hidden" name="agent" value="{{ $config_val->mc_merchant_code }}">
        <button class="btn btn-block btn-success btn-lg click-if-alone" type="submit">
            <strong> {{translate('AirtelMoney')}}</strong>
        </button>
    </form>
</div>
</body>

</html>
