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

<body class="bg-stone-100 dark:bg-[#0a0a0a] text-stone-500 flex p-6 lg:p-8 items-center justify-center min-h-screen flex-col">

    <main class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
    <div class="max-w-xl text-sm leading-sm flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] dark:text-[#EDEDEC] shadow-lg rounded-es-lg rounded-ee-lg lg:rounded-ss-lg lg:rounded-ee-none">
        <div class="mb-8">
            <img class="h-20 w-20"
                src="/apple-touch-icon.png"
                alt="Logo">
        </div>
        <h1 class="mb-1 text-xl font-medium text-stone-900">Monitor</h1>
        <p class="mb-8">
            Application de monitoring pour athlète et entraîneurs.
        </p>

        <div class="space-x-4 mx-auto">
            <a class="text-center transition duration-150 ease-in-out hover:text-stone-900" href="#">
                Admin
            </a>
        </div>

        <div class="mt-12 text-xs">
            <p>Made with ♥️ in Switzerland by <a class="underline" href="https://michaelravedoni.ch">Michael Ravedoni</a></p>
        </div>
    </div>
</main>

</body>

</html>
