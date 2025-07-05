<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Monitor</title>

    <link href="/favicon.png"
        rel="icon"
        sizes="any">
    <link href="/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.bunny.net" rel="preconnect">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament.css'])
</head>

<body class="flex min-h-screen items-center justify-center bg-stone-100 font-sans text-stone-800 antialiased dark:bg-[#161615] dark:text-[#EDEDEC]">

    <div class="mx-auto max-w-4xl rounded-lg bg-white px-6 py-12 text-center shadow-lg">
        <div class="mb-8">
            <img class="mx-auto h-20 w-20"
                src="/apple-touch-icon.png"
                alt="Logo">
        </div>
        <h1 class="mb-4 text-4xl font-extrabold text-stone-900">
            Monitor
        </h1>
        <p class="mb-8 text-lg text-stone-600">
            Application de monitoring pour athlète et entraîneurs.
        </p>

        <div class="space-x-4">
            <a class="inline-flex items-center rounded-md border border-stone-300 bg-white px-6 py-3 text-base font-medium text-stone-700 transition duration-150 ease-in-out hover:bg-stone-50 focus:outline-none focus:ring-2 focus:ring-stone-500 focus:ring-offset-2" href="#">
                Admin
            </a>
        </div>

        <div class="mt-12 text-sm text-stone-500">
            <p>Made with ♥️ in Switzerland by <a class="underline" href="https://michaelravedoni.ch">Michael Ravedoni</a></p>
        </div>
    </div>

</body>

</html>
