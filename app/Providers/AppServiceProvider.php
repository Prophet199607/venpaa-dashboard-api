<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Google\Client;
use App\Filesystem\GoogleDriveAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('google', function ($app, $config) {
            $clientId = $config['clientId'] ?? null;
            $clientSecret = $config['clientSecret'] ?? null;
            $refreshToken = $config['refreshToken'] ?? null;
            $folderId = $config['folderId'] ?? null;

            if (empty($clientId) || empty($clientSecret) || empty($refreshToken) || empty($folderId)) {
                throw new \InvalidArgumentException(
                    'Google Drive is not configured. Set GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET, GOOGLE_DRIVE_REFRESH_TOKEN, and GOOGLE_DRIVE_FOLDER_ID in .env'
                );
            }

            $client = new Client();
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);

            $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($token['error'])) {
                $message = $token['error_description'] ?? $token['error'];

                throw new \RuntimeException(
                    "Google Drive authentication failed: {$message}. Regenerate GOOGLE_DRIVE_REFRESH_TOKEN using Google Cloud OAuth Playground."
                );
            }

            $client->setAccessToken($token);

            $service = new \Google\Service\Drive($client);
            $adapter = new GoogleDriveAdapter($service, $folderId);

            return new Filesystem($adapter);
        });
    }
}
