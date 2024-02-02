<!DOCTYPE html>
<html lang="en">

<head>
    <title>{{translate('Worldpay PAYMENT')}}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/toastr.css')}}">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/world-pay.css')}}">
    <script src="{{asset('Modules/Gateways/public/assets/modules/js/jquery.min.js')}}"></script>
    <script src="{{asset('Modules/Gateways/public/assets/modules/js/toastr.js')}}"></script>
</head>

<body>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class=" col-lg-6 col-md-8">
            <div class="card p-3">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <h2 class="heading text-center">{{translate('World Pay')}}</h2>
                    </div>
                </div>
                <form action="{{route('worldpay.payment',['payment_id'=>$payment_data->id])}}" class="form-card"
                      method="post" id="wp_payment_form">
                    @csrf
                    <input type="hidden" name="session_id" id="sessionId">
                    <div class="row justify-content-center">
                        <div class="col-12">
                            <div class="input-group"><input type="text" name="Name" value="{{old('Name')}}"
                                                            placeholder="{{translate('John Doe')}}">
                                <label>{{translate('Card holder name')}}</label></div>
                        </div>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-12">
                            <div class="input-group"><input type="text" id="cr_no" name="card_no"
                                                            value="{{old('card_no')}}"
                                                            placeholder="{{translate('0000 0000 0000 0000')}}"
                                                            minlength="19" maxlength="19">
                                <label>{{translate('Card Number')}}</label></div>
                        </div>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-12">
                            <div class="row">
                                <div class="col-6">
                                    <div class="input-group"><input type="text" id="exp" name="expdate"
                                                                    placeholder="{{translate('MM/YY')}}" minlength="5"
                                                                    maxlength="5"
                                                                    value="{{old('expdate')}}">
                                        <label>{{translate('Expiry Date')}}</label></div>
                                </div>
                                <div class="col-6">
                                    <div class="input-group"><input type="password" name="cvv"
                                                                    minlength="3"
                                                                    maxlength="3" value="{{old('cvv')}}">
                                        <label>{{translate('CVV')}}</label></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-md-12"><input type="submit"
                                                      value="Pay {{$payment_data->payment_amount}} {{$payment_data->currency_code}}"
                                                      class="btn btn-pay placeicon"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<iframe height="1" width="1" name="myfram" src="about:blank">
</iframe>
{!! Toastr::message() !!}

@if ($errors->any())
    <script>
        @foreach($errors->all() as $error)
        toastr.error('{{$error}}', Error, {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif

<script src="{{asset('Modules/Gateways/public/assets/modules/js/worldpay.js')}}"></script>

</body>
</html>
