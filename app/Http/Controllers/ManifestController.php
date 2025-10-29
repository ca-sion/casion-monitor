<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManifestController extends Controller
{
    /**
     * Generate the dynamic manifest.json file.
     */
    public function generate()
    {
        $user = null;
        if (Auth::guard('athlete')->check()) {
            $user = Auth::guard('athlete')->user();
        } elseif (Auth::guard('trainer')->check()) {
            $user = Auth::guard('trainer')->user();
        }

        if (!$user) {
            abort(401, 'Unauthorized');
        }

        $manifest = [
            'name' => 'Casion Monitor - ' . $user->name,
            'short_name' => 'Casion Monitor',
            'start_url' => $user->accountLink,
            'display' => 'standalone',
            'background_color' => '#FFFFFF',
            'theme_color' => '#000000',
            'description' => 'Votre application de monitoring sportif personnalisée.',
            'icons' => [
                [
                    'src' => url('favicon.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => url('apple-touch-icon.png'),
                    'sizes' => '180x180',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ]
            ]
        ];

        return response()->json($manifest);
    }
}