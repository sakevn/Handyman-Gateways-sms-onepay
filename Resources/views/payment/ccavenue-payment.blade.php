<!DOCTYPE html>
<html lang="en">

<head>
    <title>{{translate('CCAvenue Payment')}}</title>
    <link rel="stylesheet" href="{{asset('Modules/Gateways/public/assets/modules/css/common.css')}}">
</head>

<body>
<div class="text-center">
    <form method="post" name="redirect"
          action="https://secure.ccavenue.ae/transaction/transaction.do?command=initiateTransaction">
        <input type=hidden name=encRequest value={{ $encrypted_data }}>
        <input type=hidden name=access_code value={{ $access_code }}>
    </form>
</div>

<script src="{{asset('Modules/Gateways/public/assets/modules/js/cc-avenue.js')}}"></script>

</body>
</html>
