@php
    $lang = app()->getLocale();
    $user = null;
    if (auth()->guard('athlete')->check()) {
        $user = auth()->guard('athlete')->user();
        $userModel = 'Athlete';
    } elseif (auth()->guard('trainer')->check()) {
        $user = auth()->guard('trainer')->user();
        $userModel = 'Athlete';
    }
@endphp

@if ($user)
<link rel="manifest" href="{{ route('manifest.generate', ['lang' => $lang, 'userModel' => $userModel, 'user' => $user]) }}">
@else
<link rel="manifest" href="/manifest.json">
@endif
