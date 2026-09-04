<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Contracts;

use Illuminate\Http\UploadedFile;

interface FilenameGeneratorContract
{
    public function __construct(string $filenameStrategy = 'original');

    public function resolve(UploadedFile|string $file, ?string $precomputedHash = null): string;

    public function contentHashAlgorithm(): ?string;
}
