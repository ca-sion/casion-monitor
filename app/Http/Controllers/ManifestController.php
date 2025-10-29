<?php

namespace App\Http\Controllers;

class ManifestController extends Controller
{
    /**
     * Generate the dynamic manifest.json file.
     */
    public function generate()
    {
        $lang = request()->input('lang');
        $userId = request()->input('user');
        $userModel = request()->input('userModel');
        $userModelName = 'App\\Models\\'.$userModel;
        $user = $userModelName::find($userId);

        if (! $user) {
            abort(401, 'Unauthorized');
        }

        $manifest = [
            'name'             => 'CA Sion Monitor - '.$user->name,
            'short_name'       => 'Monitor',
            'start_url'        => $user->accountLink,
            'display'          => 'standalone',
            'background_color' => '#FFFFFF',
            'theme_color'      => '#000000',
            'description'      => 'Application de monitoring pour le CA Sion.',
            'icons'            => [
                [
                    'src'     => url('favicon.png'),
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src'     => url('apple-touch-icon.png'),
                    'sizes'   => '180x180',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ];

        return response()->json($manifest);
    }
}
