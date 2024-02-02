<html xmlns='https://www.w3.org/1999/xhtml' lang="en">
<head>
    <title>{{translate('Amazon Pay')}}</title>
</head>
<body>
<form action="{{ $redirectUrl }}" method="post" id="form1" name="from1">
    @foreach ($requestParams as $a => $b)
        <input type="hidden" name="{{ $a }}" value="{{ $b }}">
    @endforeach

    <script src="{{asset('Modules/Gateways/public/assets/modules/js/amazon-pay.js')}}"></script>
</form>
</body>
</html>
