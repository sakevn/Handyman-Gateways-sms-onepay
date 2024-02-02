<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/payment/bootstrap.min.css')}}">
    <title>{{translate('Iyzipay Payment')}}</title>
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/iyzipay.css')}}">
</head>
<body>
<div class="container container-body">
    <div class="w-100">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-7 col-xl-5">
                <div class="card">
                    <div class="card-body">
                        <form action="{{route('iyzipay.payment', ['payment_id' => $payment_id])}}" method="get">
                            <input type="hidden" name="payment_id" class="form-control" value="{{$payment_id}}">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="input-label">{{translate('zipcode')}}</label>
                                    <input type="number" name="zip" class="form-control"
                                           placeholder="{{translate("zipcode")}}" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="input-label">{{translate('city')}}</label>
                                    <input type="text" name="city" class="form-control"
                                           placeholder="{{translate('city')}}" required>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex gap-3 justify-content-end">
                                        <button type="reset" id="reset_btn"
                                                class="btn btn--reset">{{translate('Reset')}}</button>
                                        <button type="submit" class="btn btn-primary">{{translate("Next")}}</button>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
