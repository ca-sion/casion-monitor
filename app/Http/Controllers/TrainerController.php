<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class TrainerController extends Controller
{
    public function dashboard(): View
    {
        $trainer = Auth::guard('trainer')->user();

        return view('trainers.dashboard', [
            'trainer' => $trainer,
        ]);
    }

    public function athlete(string $hash, Athlete $athlete): View
    {
        $trainer = Auth::guard('trainer')->user();

        return view('trainers.athlete', [
            'trainer' => $trainer,
            'athlete' => $athlete,
        ]);
    }
}
