<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>
        {{translate('Esewa Payment')}}
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/esewa.css')}}">
</head>
<body>

<main>
    <section class="payment-form dark">
        <div class="container mt-5">
            <div class="row">
                <div class="col-md-6 mb-4 cursor-pointer">
                    <div class="card">
                        <div class="card-body h-100px">
                            <form
                                action="{{$config_mode == 'test' ? 'https://uat.esewa.com.np/epay/main':'https://merchant.esewa.com.np'}}"
                                method="POST">
                                <input value="{{$data->payment_amount}}" name="tAmt" type="hidden">
                                <input value="{{$data->payment_amount}}" name="amt" type="hidden">
                                <input value="0" name="txAmt" type="hidden">
                                <input value="0" name="psc" type="hidden">
                                <input value="0" name="pdc" type="hidden">
                                <input value="EPAYTEST" name="scd" type="hidden">
                                <input value="{{$data->id}}" name="pid" type="hidden">
                                <input value="{{ route('esewa.verify', ['payment_id' => $data->id]) }}?q=su"
                                       type="hidden" name="su">
                                <input value="{{ route('esewa.verify', ['payment_id' => $data->id]) }}?q=fu"
                                       type="hidden" name="fu">
                                <button class="btn btn-block click-if-alone" type="submit">
                                    <img width="150"
                                         src="{{asset('Modules/Gateways/public/assets/modules/image/esewa.png')}}"/>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>
</body>
</html>
