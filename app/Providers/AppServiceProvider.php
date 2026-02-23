<?php

namespace App\Providers;

use App\Contracts\AttachmentScannerInterface;
use App\Services\WhatsApp\AttachmentScanners\ClamAvScanner;
use App\Services\WhatsApp\AttachmentScanners\NullScanner;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AttachmentScannerInterface::class, function () {
            return config('whatsapp.attachments.scanner') === 'clamav'
                ? new ClamAvScanner
                : new NullScanner;
        });

        // Force HTTPS in production environment
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix Livewire asset URL for sub-directory deployment.
        // When APP_URL includes a path (e.g. /Smart-Dining), the Livewire
        // script tag must include that prefix so the browser requests the
        // correct URL instead of a bare /livewire/livewire.js (which 404s).
        $appPath = parse_url(config('app.url', ''), PHP_URL_PATH);

        if ($appPath && $appPath !== '/') {
            $prefix = rtrim($appPath, '/');
            $file = config('app.debug') ? 'livewire.js' : 'livewire.min.js';
            config(['livewire.asset_url' => "{$prefix}/livewire/{$file}"]);
        }
    }
}
