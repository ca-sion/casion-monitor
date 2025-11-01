@php
    $lang = app()->getLocale();
    $user = null;
    $userModel = null;

    if (request()->routeIs('trainers.*')) {
        if (auth()->guard('trainer')->check()) {
            $user = auth()->guard('trainer')->user();
            $userModel = 'Trainer';
        }
    } else {
        if (auth()->guard('athlete')->check()) {
            $user = auth()->guard('athlete')->user();
            $userModel = 'Athlete';
        }
    }
@endphp

@if ($user)
<link rel="manifest" href="{{ route('manifest.generate', ['lang' => $lang, 'userModel' => $userModel, 'user' => $user]) }}">
@else
<link rel="manifest" href="/manifest.json">
@endif
