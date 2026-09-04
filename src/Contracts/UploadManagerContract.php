<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Contracts;

use ErvinsVilumsons\LaravelUpload\Upload\UploadResult;
use Illuminate\Http\UploadedFile;

interface UploadManagerContract
{
    public static function profile(string $profileName): self;

    /**
     * @param array{
     *     name?: string,
     *     path?: string,
     *     hash?: bool,
     *     encrypt?: bool,
     * } $options
     */
    public function upload(UploadedFile|string $file, array $options = [], ?callable $progress = null): UploadResult;

    /**
     * @return resource
     */
    public function readStream(string $path): mixed;
}
