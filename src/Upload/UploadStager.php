<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Upload;

use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use ErvinsVilumsons\LaravelUpload\Generators\FilenameGenerator;
use Illuminate\Http\UploadedFile;

final readonly class UploadStager
{
    public function __construct(private FilenameGenerator $filenameGenerator) {}

    /** @return array{0: string, 1: ?string, 2: ?string} */
    public function stageAndHash(UploadedFile|string $file, bool $hash, ?callable $progress, ?int $totalBytes): array
    {
        $sourceStream = $this->openFileStream($file);
        [$stagedPath, $stagedStream] = $this->createStagedStream();

        if ($sourceStream === false || $stagedPath === false || $stagedStream === false) {
            $this->cleanupStreams($sourceStream, $stagedStream, $stagedPath);

            throw new UploadException('Unable to stage file for upload.');
        }

        $hashers = $this->createHashers($hash);

        try {
            $this->copyStream(
                $sourceStream,
                $stagedStream,
                $progress,
                $totalBytes,
                $hashers,
            );
        } catch (\Throwable $e) {
            fclose($stagedStream);

            if (file_exists($stagedPath)) {
                unlink($stagedPath);
            }

            throw $e;
        } finally {
            fclose($sourceStream);
        }

        $filenameAlgorithm = $this->filenameGenerator->contentHashAlgorithm();

        $filenameHash = $filenameAlgorithm !== null
            ? hash_final($hashers[$filenameAlgorithm])
            : null;

        $contentHash = $hash
            ? ($filenameAlgorithm === 'sha256'
                ? $filenameHash
                : hash_final($hashers['sha256']))
            : null;

        fclose($stagedStream);

        return [$stagedPath, $filenameHash, $contentHash];
    }

    /**
     * @param  resource|false  $sourceStream
     * @param  resource|false  $stagedStream
     */
    private function cleanupStreams(
        mixed $sourceStream,
        mixed $stagedStream,
        string|false $stagedPath,
    ): void {
        if (is_resource($sourceStream)) {
            fclose($sourceStream);
        }

        if (is_resource($stagedStream)) {
            fclose($stagedStream);
        }

        if ($stagedPath !== false && file_exists($stagedPath)) {
            unlink($stagedPath);
        }
    }

    /**
     * @param  resource  $sourceStream
     * @param  resource  $stagedStream
     * @param  array<string, \HashContext>  $hashers
     * @return int The number of bytes processed
     *
     * @throws UploadException If reading or writing fails
     */
    public function copyStream(
        mixed $sourceStream,
        mixed $stagedStream,
        ?callable $progress,
        ?int $totalBytes,
        array &$hashers = [],
    ): int {
        if (! is_resource($sourceStream) || ! is_resource($stagedStream)) {
            throw new UploadException('Unable to copy file stream.');
        }

        $processed = 0;

        if ($progress !== null) {
            $progress(0, $totalBytes);
        }

        while (! feof($sourceStream)) {
            $chunk = fread($sourceStream, 8192);

            if ($chunk === false) {
                throw new UploadException('Unable to read file while copying.');
            }

            if ($chunk === '') {
                continue;
            }

            $written = fwrite($stagedStream, $chunk);

            if ($written !== strlen($chunk)) {
                throw new UploadException('Unable to write file while copying.');
            }

            foreach ($hashers as $hasher) {
                hash_update($hasher, $chunk);
            }

            $processed += $written;

            if ($progress !== null) {
                $progress($processed, $totalBytes);
            }
        }

        return $processed;
    }

    /**
     * @return array<string, \HashContext>
     */
    private function createHashers(bool $hash): array
    {
        $algorithm = $this->filenameGenerator->contentHashAlgorithm();

        $hashers = $algorithm !== null
            ? [$algorithm => hash_init($algorithm)]
            : [];

        if ($hash && ! isset($hashers['sha256'])) {
            $hashers['sha256'] = hash_init('sha256');
        }

        return $hashers;
    }

    /**
     * @return array{0: string|false, 1: resource|false}
     */
    private function createStagedStream(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel-upload-');

        if ($path === false) {
            return [false, false];
        }

        $stream = fopen($path, 'wb');

        if ($stream === false) {
            unlink($path);

            return [false, false];
        }

        return [$path, $stream];
    }

    /**
     * @return resource|false
     */
    public function openFileStream(UploadedFile|string $file)
    {
        if ($file instanceof UploadedFile) {
            $path = $file->getRealPath();

            return $path !== false ? fopen($path, 'rb') : false;
        }

        if (file_exists($file)) {
            return fopen($file, 'rb');
        }

        return false;
    }
}
