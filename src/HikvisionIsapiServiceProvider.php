<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi;

use Illuminate\Support\ServiceProvider;
use Shaykhnazar\HikvisionIsapi\Authentication\Contracts\AuthenticatorInterface;
use Shaykhnazar\HikvisionIsapi\Authentication\DigestAuthenticator;
use Shaykhnazar\HikvisionIsapi\Client\Contracts\HttpClientInterface;
use Shaykhnazar\HikvisionIsapi\Client\DeviceManager;
use Shaykhnazar\HikvisionIsapi\Client\HttpClient;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

class HikvisionIsapiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/hikvision.php',
            'hikvision'
        );

        // Bind interfaces to implementations
        $this->app->bind(AuthenticatorInterface::class, DigestAuthenticator::class);
        $this->app->bind(HttpClientInterface::class, HttpClient::class);

        // Register Device Manager as singleton (for multi-device support)
        $this->app->singleton(DeviceManager::class, function ($app) {
            // Check if custom device provider is bound
            if ($app->bound('hikvision.device.provider')) {
                $provider = $app->make('hikvision.device.provider');
            } else {
                // Use config by default (backward compatibility)
                $provider = config('hikvision');
            }

            return new DeviceManager(
                $app->make(HttpClientInterface::class),
                $app->make(AuthenticatorInterface::class),
                $provider
            );
        });

        // Register main client as singleton (uses default device for backward compatibility)
        $this->app->singleton(HikvisionClient::class, function ($app) {
            return $app->make(DeviceManager::class)->default();
        });

        // Register services
        $this->registerServices();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Config/hikvision.php' => config_path('hikvision.php'),
            ], 'hikvision-config');
        }
    }

    private function registerServices(): void
    {
        $services = [
            'DeviceService',
            'PersonService',
            'CardService',
            'FaceService',
            'FingerprintService',
            'AccessControlService',
            'EventService',
        ];

        foreach ($services as $service) {
            $class = "Shaykhnazar\\HikvisionIsapi\\Services\\{$service}";
            $this->app->singleton($class, fn($app) => new $class($app->make(HikvisionClient::class)));
        }
    }
}
