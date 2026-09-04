<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Generators;

use ErvinsVilumsons\LaravelUpload\Contracts\FilenameGeneratorContract;
use ErvinsVilumsons\LaravelUpload\Hash\StreamHasher;
use ErvinsVilumsons\LaravelUpload\Upload\UploadFileMetadata;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * FilenameGenerator with stream-aware hashing.
 *
 * Generates filenames using various strategies:
 * - 'uuid': Random UUID
 * - 'md5', 'sha1', 'sha256': Hash of file content (stream-based, memory-efficient)
 * - 'original': Original uploaded filename
 */
final class FilenameGenerator implements FilenameGeneratorContract
{
    protected string $filenameStrategy;

    private readonly StreamHasher $streamHasher;

    private readonly UploadFileMetadata $metaData;

    const array SUPPORTED_STRATEGIES = [
        'uuid',
        'md5',
        'sha1',
        'sha256',
        'original',
    ];

    const array HASHING_STRATEGIES = [
        'md5',
        'sha1',
        'sha256',
    ];

    public function __construct(string $filenameStrategy = 'original')
    {
        if (! in_array($filenameStrategy, self::SUPPORTED_STRATEGIES, true)) {
            throw new \InvalidArgumentException("Unsupported filename strategy: {$filenameStrategy}");
        }

        $this->filenameStrategy = $filenameStrategy;
        $this->streamHasher = new StreamHasher;
        $this->metaData = new UploadFileMetadata;
    }

    /**
     * Resolve filename using the configured strategy.
     *
     * @param  UploadedFile|string  $file  The file to generate a name for
     * @return string The generated filename with extension
     *
     * @throws \RuntimeException If hashing fails
     */
    public function resolve(UploadedFile|string $file, ?string $precomputedHash = null): string
    {
        $extension = $this->metaData->extension($file);

        switch ($this->filenameStrategy) {
            case 'uuid':
                return Str::uuid()->toString().'.'.$extension;

            case 'md5':
            case 'sha1':
            case 'sha256':
                $fileHash = $precomputedHash ?? $this->streamHasher->hash($file, $this->filenameStrategy);

                return $fileHash.'.'.$extension;

            default:
                return $this->metaData->originalName($file);
        }
    }

    public function contentHashAlgorithm(): ?string
    {
        return in_array($this->filenameStrategy, self::HASHING_STRATEGIES, true) ? $this->filenameStrategy : null;
    }
}
