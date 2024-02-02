<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>
        @yield('title')
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/maxicash.css')}}">
</head>
<body>
<main>
    <section class="payment-form dark">
        <div class="container">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="container__payment text-center mt-5 d-flex justify-content-center">
                        <div class="form-payment">
                            <div class="products">
                                <h2 class="mb-5">{{translate('Card Payment')}}</h2>
                                <p class="alert alert-danger d--none" role="alert" id="error_alert"></p>
                            </div>
                            <div class="modal-body">
                                <form action="{{route('maxicash.index')}}">
                                    <div class="form-group">
                                        <label for="Telephone"
                                               class="col-form-label">{{translate('Telephone:')}}</label>
                                        <input type="tel" class="form-control" id="Telephone" name='tel' required>
                                        <input type="hidden" name="payment_id" value="{{$payment_data['id']}}">
                                    </div>
                                    <div class="form-group">
                                        <button type="submit"
                                                class="btn btn-primary">{{translate("Pay")}} {{$payment_data->payment_amount}}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
</body>
</html>
