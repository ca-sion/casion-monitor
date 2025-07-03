<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class AthleteController extends Controller
{
    public function dashboard(): View
    {
        $athlete = Auth::guard('athlete')->user();

        return view('athletes.dashboard', [
            'athlete' => $athlete,
        ]);
    }
}
