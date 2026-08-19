<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Load helpers manually to avoid requiring composer dump-autoload on hosting
        if (file_exists(app_path('Helpers/WorkflowHelper.php'))) {
            require_once app_path('Helpers/WorkflowHelper.php');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Google Drive Storage Driver
        \Illuminate\Support\Facades\Storage::extend('google', function($app, $config) {
            $client = new \Google_Client();
            
            $credentialsPath = storage_path('app/google-drive-credentials.json');
            if (file_exists($credentialsPath)) {
                putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentialsPath);
                $client->useApplicationDefaultCredentials();
            }
            
            $client->addScope(\Google_Service_Drive::DRIVE);
            $service = new \Google_Service_Drive($client);
            
            $adapter = new \Masbug\Flysystem\GoogleDriveAdapter($service, $config['folder'] ?? '/', ['useHasDir' => true]);
            
            return new \Illuminate\Filesystem\FilesystemAdapter(
                new \League\Flysystem\Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        // Paksa HTTPS jika diakses melalui proxy Ngrok atau protokol HTTPS
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        } elseif (app()->environment('production') || str_contains(request()->getHost(), 'ngrok')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Inject Integration Settings from DB to Config
        try {
            if (!app()->runningInConsole()) {
                $settings = \Illuminate\Support\Facades\Cache::remember('app_integration_settings', 3600, function () {
                    return \App\Models\Setting::whereIn('key', [
                        'pusher_app_id',
                        'pusher_key',
                        'pusher_secret',
                        'pusher_cluster',
                        'beams_instance_id',
                        'beams_secret_key'
                    ])->pluck('value', 'key')->toArray();
                });

                if (!empty($settings['pusher_app_id'])) \Illuminate\Support\Facades\Config::set('broadcasting.connections.pusher.app_id', $settings['pusher_app_id']);
                if (!empty($settings['pusher_key'])) \Illuminate\Support\Facades\Config::set('broadcasting.connections.pusher.key', $settings['pusher_key']);
                if (!empty($settings['pusher_secret'])) \Illuminate\Support\Facades\Config::set('broadcasting.connections.pusher.secret', $settings['pusher_secret']);
                if (!empty($settings['pusher_cluster'])) {
                    \Illuminate\Support\Facades\Config::set('broadcasting.connections.pusher.options.cluster', $settings['pusher_cluster']);
                    \Illuminate\Support\Facades\Config::set('broadcasting.connections.pusher.options.host', 'api-'.$settings['pusher_cluster'].'.pusher.com');
                }
                
                if (!empty($settings['beams_instance_id'])) \Illuminate\Support\Facades\Config::set('beams.instance_id', $settings['beams_instance_id']);
                if (!empty($settings['beams_secret_key'])) \Illuminate\Support\Facades\Config::set('beams.secret_key', $settings['beams_secret_key']);
            }
        } catch (\Exception $e) {
            // Abaikan error (misal saat DB belum di-migrate)
        }

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
