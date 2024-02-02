@extends('Gateways::payment.layouts.master')

@push('script')
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/common.css')}}">
    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
@endpush

@section('content')
    <h1 class="text-center">{{translate('Please do not refresh this page')}}...</h1>

    <script type="text/javascript">
        'use strict';
        var session_id = "{{$session_id}}";
        var paymentId = "{{ $data['id'] }}";
        var orderId = "{{ $order_id }}";
        var productionStatus = "{{$production_status}}";

        var callbackUrl = "{{ url('/payment/cashfree/callback') }}" + '?payment_id=' + paymentId + '&order_id=' + orderId;

        document.addEventListener("DOMContentLoaded", function () {

            const cashfree = Cashfree({
                mode: productionStatus
            });

            let checkoutOptions = {
                paymentSessionId: session_id,
                returnUrl: callbackUrl,
            }
            cashfree.checkout(checkoutOptions).then(function (result) {
                if (result.error) {
                    alert(result.error.message)
                }
                if (result.redirect) {
                    console.log("Redirection")
                }
            });
        });

    </script>
@endsection
