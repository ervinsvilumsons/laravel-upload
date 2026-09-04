<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Upload;

use ErvinsVilumsons\LaravelUpload\Encryption\AesEncryptionProvider;
use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;

final readonly class UploadStorage
{
    public function __construct(private Filesystem|FilesystemAdapter $disk, private UploadStager $stager, private AesEncryptionProvider $encryptionProvider, private bool $encrypt) {}

    public function store(UploadedFile|string $file, string $path, ?callable $progress, ?int $totalBytes): bool
    {
        $sourceStream = $this->stager->openFileStream($file);
        if ($sourceStream === false) {
            throw new UploadException('Unable to open file for upload.');
        }
        try {
            if ($this->encrypt) {
                return $this->encryptAndStore($sourceStream, $path, $progress, $totalBytes);
            }
            $uploadStream = tmpfile();
            if ($uploadStream === false) {
                throw new UploadException('Unable to create temporary stream for upload.');
            }
            try {
                $this->stager->copyStream($sourceStream, $uploadStream, $progress, $totalBytes);
                rewind($uploadStream);
                $uploaded = $this->disk->put($path, $uploadStream);
            } finally {
                fclose($uploadStream);
            }
            if ($uploaded === false) {
                throw new UploadException('Failed to upload file to storage.');
            }

            return (bool) $uploaded;
        } finally {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }
        }
    }

    /** @return resource */
    public function readStream(string $path): mixed
    {
        $storedStream = $this->disk->readStream($path);
        if ($storedStream === null) {
            throw new UploadException('Unable to open stored file for reading.');
        }
        if (! $this->encrypt) {
            return $storedStream;
        }
        $plainStream = tmpfile();
        if ($plainStream === false) {
            fclose($storedStream);
            throw new UploadException('Unable to create temporary stream for decryption.');
        }
        try {
            if (! $this->encryptionProvider->decryptStream($storedStream, $plainStream)) {
                throw new UploadException('Unable to decrypt stored file.');
            }
            rewind($plainStream);
            fclose($storedStream);

            return $plainStream;
        } catch (\Throwable $e) {
            if (is_resource($storedStream)) {
                fclose($storedStream);
            }
            if (is_resource($plainStream)) {
                fclose($plainStream);
            }
            if ($e instanceof UploadException) {
                throw $e;
            }
            throw new UploadException('Failed to read encrypted file.', 0, $e);
        }
    }

    private function encryptAndStore(mixed $sourceStream, string $path, ?callable $progress, ?int $totalBytes): bool
    {
        $encryptedStream = tmpfile();
        if ($encryptedStream === false) {
            throw new UploadException('Unable to create temporary stream for encryption.');
        }
        try {
            if (! $this->encryptionProvider->encryptStream($sourceStream, $encryptedStream, 8192, $progress, $totalBytes)) {
                throw new UploadException('Encryption failed.');
            }
            if (fseek($encryptedStream, 0) !== 0) {
                throw new UploadException('Unable to rewind encrypted stream.');
            }
            $uploaded = $this->disk->put($path, $encryptedStream);
            if ($uploaded === false) {
                throw new UploadException('Failed to upload encrypted file to storage.');
            }

            return (bool) $uploaded;
        } catch (\Throwable $e) {
            if ($e instanceof UploadException) {
                throw $e;
            }
            throw new UploadException('Failed to encrypt and upload file.', 0, $e);
        } finally {
            fclose($encryptedStream);
        }
    }
}
