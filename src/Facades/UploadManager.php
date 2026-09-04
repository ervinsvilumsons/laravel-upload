<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Facades;

use ErvinsVilumsons\LaravelUpload\Upload\UploadResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;

/**
 * @see \ErvinsVilumsons\LaravelUpload\UploadManager
 *
 * @method static self profile(string $profileName)
 * @method static UploadResult upload(UploadedFile|string $file, string $filename = null)
 */
class UploadManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ErvinsVilumsons\LaravelUpload\UploadManager::class;
    }
}
