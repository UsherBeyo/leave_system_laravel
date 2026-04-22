<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Leave System')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('pictures/DEPED.jpg') }}">
    @stack('head')
</head>
<body class="login-page">
@yield('content')
@stack('scripts')
</body>
</html>
