<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/font-awesome.css')}}">

    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/toastr.css')}}">
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/swish-payment.css')}}">
    <title>{{translate('Swish Payment Gateway')}}</title>
</head>
<body>

<section class="login-block">

    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <form class="md-float-material form-material" id="my-form">
                    @csrf
                    <div class="auth-box card">
                        <div class="card-block">
                            <div class="row">
                                <div class="col-md-12">
                                    <h3 class="text-center"><img width="150"
                                                                 src="{{asset('Modules/Gateways/public/assets/modules/image/swish.png')}}"/>
                                    </h3>
                                </div>
                            </div>
                            <div class="form-group form-primary">
                                <input type="text" name="number" class="form-control text-center"
                                       placeholder="{{translate('Enter mobile phone number')}}" required>

                            </div>
                            <input value="{{$payment_data->attribute_id}}" name="order_id" type="hidden">
                            <input value="{{$payment_data->id}}" name="payment_link_id" type="hidden">
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit"
                                            class="btn btn-primary btn-md btn-block waves-effect text-center m-b-20"
                                            id="btnSubmit"><b> {{translate('Pay with Swish')}} </b></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <div id="output"></div>
            </div>

        </div>

    </div>

</section>


<!-- The Modal -->
<div class="modal fade" id="myModal">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h4 class="modal-title">{{translate('Follow the steps')}}</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">
                <div class="alert alert-primary">
                    <span><i class="fa fa-arrow-circle-right" aria-hidden="true"></i></span>
                    <span>{{translate('Please opens the Swish app on your phone')}}</span>
                </div>
                <div class="alert alert-primary">
                    <span><i class="fa fa-arrow-circle-right" aria-hidden="true"></i></span>
                    <span>{{translate('A payment request appears in your app. You have to sign in with your BankID')}}</span>
                </div>
                <div class="alert alert-primary">
                    <span><i class="fa fa-arrow-circle-right" aria-hidden="true"></i></span>
                    <span>{{translate('A confirmation is shown in your swish app. Confirm the payment, Please!')}}</span>
                </div>
            </div>
            <div class="d-flex justify-content-center">
                <div class="spinner-border" role="status">
                </div>
                </br>
                <span class="h6">{{translate('Waiting for payment')}}...</span>
            </div>
            </br>

        </div>
    </div>
</div>

<script src="{{asset('Modules/Gateways/public/assets/modules/js/jquery.min.js')}}"></script>
<script src="{{asset('Modules/Gateways/public/assets/modules/js/bootstrap.bundle.min.js')}}"></script>
<script src="{{asset('Modules/Gateways/public/assets/modules/js/toastr.js')}}"></script>
{!! Toastr::message() !!}

<script type="text/javascript">
    'use strict';
    $(document).ready(function () {

        $("#btnSubmit").click(function (event) {

            event.preventDefault();

            var form = $('#my-form')[0];

            var data = new FormData(form);

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.ajax({
                type: "POST",
                url: "{{route('swish.make-payment')}}",
                data: data,
                processData: false,
                contentType: false,
                cache: false,
                beforeSend: function () {
                    $('#myModal').modal('show');
                },
                success: function (data) {
                    var timesRun = 0;
                    if (data.status == 200) {
                        var interval = setInterval(function () {
                            if (timesRun === 18) {
                                clearInterval(interval);
                            }
                            $.ajax({
                                type: "GET",
                                url: "{{route('swish.check-payment')}}",
                                dataType: 'json',
                                data: {
                                    order_id: '{{$payment_data->attribute_id}}',
                                    payment_link_id: '{{$payment_data->id}}'
                                },
                                success: function (result) {
                                    console.log(result.response);
                                    if (result.response == 'success') {
                                        let url = '{{route('payment-success')}}';
                                        console.log('success')
                                        window.location.replace(url);
                                    } else {

                                        let url = '{{route('payment-fail')}}';
                                        console.log('failed')
                                        window.location.replace(url);
                                    }

                                    console.log(result.response);
                                }

                            });
                            timesRun += 1;
                        }, 3000);

                        console.log(data.id);
                        $('#payment_id').val(data.id);
                        // $('#myModal').modal('show');
                        $("#btnSubmit").prop("disabled", false);
                    } else if (data.errors) {
                        toastr.error(data.errors[0].message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    } else {
                        var result = JSON.parse(data);
                        var err = String(result[0].errorMessage);

                        toastr.error(err, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        console.log(result[0].errorMessage);
                    }

                },
                error: function (e) {
                    console.log(e.responseText);
                }
            });
        });
    });
</script>
</body>
</html>
