<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload;

use ErvinsVilumsons\LaravelUpload\Contracts\UploadManagerContract;
use ErvinsVilumsons\LaravelUpload\Encryption\AesEncryptionProvider;
use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use ErvinsVilumsons\LaravelUpload\Generators\FilenameGenerator;
use ErvinsVilumsons\LaravelUpload\Generators\PathGenerator;
use ErvinsVilumsons\LaravelUpload\Upload\UploadFileMetadata;
use ErvinsVilumsons\LaravelUpload\Upload\UploadResult;
use ErvinsVilumsons\LaravelUpload\Upload\UploadSettings;
use ErvinsVilumsons\LaravelUpload\Upload\UploadStager;
use ErvinsVilumsons\LaravelUpload\Upload\UploadStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadManager implements UploadManagerContract
{
    protected Filesystem|FilesystemAdapter $disk;

    protected bool $hash = false;

    protected bool $encrypt = false;

    protected FilenameGenerator $filenameGenerator;

    protected PathGenerator $pathGenerator;

    private readonly UploadFileMetadata $metadata;

    private readonly UploadStager $stager;

    private readonly UploadStorage $storage;

    /** @param array<string, mixed> $settings */
    public function __construct(array $settings)
    {
        $diskName = $settings['disk'] ?? config('filesystems.default', 'local');
        $this->disk = Storage::disk(is_string($diskName) ? $diskName : 'local');
        $encrypt = $settings['encrypt'] ?? false;
        $this->encrypt = is_bool($encrypt) ? $encrypt : (bool) $encrypt;
        $hash = $settings['hash'] ?? false;
        $this->hash = is_bool($hash) ? $hash : (bool) $hash;

        $filename = $settings['filename'] ?? 'original';
        $this->filenameGenerator = new FilenameGenerator(is_string($filename) ? $filename : 'original');
        $path = $settings['path'] ?? '/';
        $this->pathGenerator = new PathGenerator(is_string($path) ? $path : '/');
        $this->metadata = new UploadFileMetadata;
        $this->stager = new UploadStager($this->filenameGenerator);
        $this->storage = new UploadStorage($this->disk, $this->stager, new AesEncryptionProvider, $this->encrypt);
    }

    public static function profile(string $profileName): self
    {
        return new self(array_replace(
            UploadSettings::normalize(config('upload-manager', [])),
            UploadSettings::profile($profileName),
        ));
    }

    public function upload(UploadedFile|string $file, array $options = [], ?callable $progress = null): UploadResult
    {
        if (! $this->disk instanceof FilesystemAdapter) {
            throw new UploadException('The configured filesystem does not support URLs.');
        }

        $stagedPath = null;
        $filenameHash = null;
        $contentHash = null;
        $uploadFile = $file;
        $fileSize = $this->metadata->size($file);
        $this->metadata->validateSize($file, $fileSize);
        $this->hash = $options['hash'] ?? $this->hash;
        $this->encrypt = $options['encrypt'] ?? $this->encrypt;

        if ($this->hash || $this->filenameGenerator->contentHashAlgorithm() !== null) {
            [$stagedPath, $filenameHash, $contentHash] = $this->stager->stageAndHash($file, $this->hash, $progress, $fileSize);
            $uploadFile = $stagedPath;
        }

        $name = $options['name'] ?? $this->filenameGenerator->resolve($file, $filenameHash);

        if ($name === '') {
            $name = $this->metadata->originalName($file);
        }

        $path = $options['path'] ?? $this->pathGenerator->resolve($name);

        try {
            if (! $this->storage->store($uploadFile, $path, $stagedPath === null ? $progress : null, $fileSize)) {
                throw new UploadException('Unable to store the uploaded file.');
            }
        } finally {
            if ($stagedPath !== null && file_exists($stagedPath)) {
                unlink($stagedPath);
            }
        }

        return new UploadResult([
            'name' => basename($path),
            'originalName' => $this->metadata->originalName($file),
            'extension' => $this->metadata->extension($file),
            'mimeType' => $this->metadata->mimeType($file),
            'size' => $fileSize,
            'path' => $path,
            'url' => $this->disk->url($path),
            'contentHash' => $contentHash,
        ]);
    }

    /** @return resource */
    public function readStream(string $path): mixed
    {
        return $this->storage->readStream($path);
    }
}
