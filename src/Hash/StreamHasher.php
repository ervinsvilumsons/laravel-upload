<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Hash;

use ErvinsVilumsons\LaravelUpload\Contracts\HashGeneratorContract;
use Illuminate\Http\UploadedFile;

/**
 * Stream-based hash generator that processes files in chunks.
 * Prevents loading entire files into memory, making it suitable for large file uploads.
 */
final class StreamHasher implements HashGeneratorContract
{
    /**
     * Compute hash of a file using stream processing.
     *
     * @param  mixed  $file  The file (UploadedFile or path string)
     * @param  string  $algorithm  The hashing algorithm (md5, sha1, sha256, etc.)
     * @param  int  $chunkSize  The size of chunks to read (default 8KB)
     * @return string The computed hash
     *
     * @throws \RuntimeException If file cannot be read or hashing fails
     */
    public function hash(mixed $file, string $algorithm = 'sha256', int $chunkSize = 8192): string
    {
        $resource = $this->getFileResource($file);

        if (! is_resource($resource)) {
            throw new \RuntimeException('Unable to open file for hashing.');
        }

        $context = hash_init($algorithm);

        try {
            while (! feof($resource)) {
                $chunk = fread($resource, max(1, $chunkSize));
                if ($chunk === false) {
                    throw new \RuntimeException('Error reading file chunk.');
                }
                if ($chunk === '') {
                    continue;
                }
                hash_update($context, $chunk);
            }

            return hash_final($context);
        } finally {
            if (is_string($file) || ! ($file instanceof UploadedFile)) {
                fclose($resource);
            }
        }
    }

    /**
     * Get a readable file resource.
     *
     * @param  mixed  $file  The file (UploadedFile or path string)
     * @return resource|false File resource or false on failure
     */
    private function getFileResource(mixed $file)
    {
        if ($file instanceof UploadedFile) {
            return fopen($file->getRealPath(), 'rb');
        }

        if (is_string($file) && file_exists($file)) {
            return fopen($file, 'rb');
        }

        return false;
    }
}
