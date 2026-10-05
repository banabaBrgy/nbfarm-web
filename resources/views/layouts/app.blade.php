<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>NB Poultry</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    @vite([
        'resources/css/app.css',
        'resources/css/dashboard.css',
        'resources/js/app.js'
    ])
    <link rel="stylesheet" href="{{ versioned_asset('css/daylight-ui.css') }}">

</head>

<body>

    @yield('content')

</body>

</html>