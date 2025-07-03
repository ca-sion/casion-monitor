<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AthleteHashProtect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hash = $request->route('hash');
        $athleteId = data_get(Hashids::connection('athlete_hash')->decode($hash), 0);
        $athleteGuard = Auth::guard('athlete');

        // Check hash validity
        if (isset($hash) && ! $athleteId) {
            return response()->view('errors.401', ['message' => 'No valid hash.'], 401);
        }

        // Check hash exists
        if (! $hash) {
            return response()->view('errors.401', ['message' => 'No hash found.'], 401);
        }

        // Check contact exists and login
        $athlete = $athleteGuard->loginUsingId($athleteId);

        if (! $athlete) {
            return response()->view('errors.403', ['message' => 'No athlete found.'], 403);
        }

        // Update
        $athlete->last_connection = now();
        $athlete->save();
        
        return $next($request);
    }
}
