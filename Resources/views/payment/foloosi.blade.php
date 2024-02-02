<!DOCTYPE html>
<html lang="en">
    <head>
    <title>{{translate('Payment')}}</title>
        <meta charset="utf-8"/>
    </head>
    <body>
        <script type="text/javascript" src="https://www.foloosi.com/js/foloosipay.v2.js"></script>
        <script type="text/javascript">
            'use strict';
            var reference_token = '{{ $reference_token }}';
            var merchant_key ='{{ $merchant_key }}';
            var options = {
                "reference_token" : reference_token,
                "merchant_key" : merchant_key,
                "redirect" : true
            }
            var fp1 = new Foloosipay(options);
            fp1.open();
        </script>
    </body>
</html>
