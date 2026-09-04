<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload;

use ErvinsVilumsons\LaravelUpload\Upload\UploadSettings;
use Illuminate\Support\ServiceProvider;

class UploadManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/upload-manager.php', 'upload-manager');

        $this->app->singleton(UploadManager::class, fn (): UploadManager => new UploadManager(UploadSettings::normalize(config('upload-manager.default', []))));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/upload-manager.php' => config_path('upload-manager.php')], 'upload-manager-config');
    }
}
