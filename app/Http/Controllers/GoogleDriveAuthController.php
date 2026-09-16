<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class GoogleDriveAuthController extends Controller
{
    public function redirect()
    {
        $clientId = Setting::where('key', 'google_drive_client_id')->value('value');
        $clientSecret = Setting::where('key', 'google_drive_client_secret')->value('value');

        if (empty($clientId) || empty($clientSecret)) {
            return redirect()->route('settings.system');
        }

        $client = new \Google_Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri(url('/auth/google-drive/callback'));
        $client->addScope(\Google\Service\Drive::DRIVE_FILE);
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // Force to get refresh token every time

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('settings.system');
        }

        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('settings.system');
        }

        $clientId = Setting::where('key', 'google_drive_client_id')->value('value');
        $clientSecret = Setting::where('key', 'google_drive_client_secret')->value('value');

        $client = new \Google_Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri(url('/auth/google-drive/callback'));

        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new \Exception($token['error_description'] ?? 'Gagal mengambil akses token.');
            }

            if (isset($token['refresh_token'])) {
                Setting::updateOrCreate(
                    ['key' => 'google_drive_refresh_token'],
                    ['value' => $token['refresh_token']]
                );
            }

            return redirect()->route('settings.system');

        } catch (\Exception $e) {
            return redirect()->route('settings.system');
        }
    }

    public function disconnect()
    {
        Setting::where('key', 'google_drive_refresh_token')->delete();
        return redirect()->route('settings.system');
    }
}
