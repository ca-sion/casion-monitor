<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrainerHashProtect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hash = $request->route('hash');
        $trainerId = data_get(Hashids::connection('trainer_hash')->decode($hash), 0);
        $trainerGuard = Auth::guard('trainer');

        // Check hash validity
        if (isset($hash) && ! $trainerId) {
            return response()->view('errors.401', ['message' => 'No valid hash.'], 401);
        }

        // Check hash exists
        if (! $hash) {
            return response()->view('errors.401', ['message' => 'No hash found.'], 401);
        }

        // Check contact exists and login
        $trainer = $trainerGuard->loginUsingId($trainerId);

        if (! $trainer) {
            return response()->view('errors.403', ['message' => 'No trainer found.'], 403);
        }

        // Update
        $trainer->last_connection = now();
        $trainer->save();
        
        return $next($request);
    }
}
