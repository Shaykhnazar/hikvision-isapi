<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi;

use Illuminate\Support\ServiceProvider;
use Shaykhnazar\HikvisionIsapi\Authentication\Contracts\AuthenticatorInterface;
use Shaykhnazar\HikvisionIsapi\Authentication\DigestAuthenticator;
use Shaykhnazar\HikvisionIsapi\Client\Contracts\HttpClientInterface;
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

        // Register main client as singleton
        $this->app->singleton(HikvisionClient::class, function ($app) {
            return new HikvisionClient(
                $app->make(HttpClientInterface::class),
                $app->make(AuthenticatorInterface::class),
                config('hikvision')
            );
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
