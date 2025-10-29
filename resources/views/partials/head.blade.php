<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ? $title.' · Monitor' : config('app.name') }}</title>

<link rel="icon" href="/favicon.png" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<meta name="theme-color" content="#000000"/>
@include('partials.manifest')

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600" rel="stylesheet" />

@filamentStyles
@fluxAppearance
@vite(['resources/css/filament.css', 'resources/css/app.css'])

<style>
    [x-cloak] {
        display: none !important;
    }
</style>
