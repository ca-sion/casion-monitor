<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ? $title.' · Monitor' : config('app.name') }}</title>

<link rel="icon" href="/favicon.png" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@filamentStyles
@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament.css'])
@fluxAppearance

<style>
    [x-cloak] {
        display: none !important;
    }
</style>
