<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Contracts;

/**
 * Stream-aware hash generator for computing file hashes without loading entire file into memory.
 */
interface HashGeneratorContract
{
    /**
     * Compute hash of a file using stream processing.
     *
     * @param  mixed  $file  The file (UploadedFile or path string)
     * @param  string  $algorithm  The hashing algorithm (md5, sha1, sha256, etc.)
     * @param  int  $chunkSize  The size of chunks to read (default 8KB)
     * @return string The computed hash
     */
    public function hash(mixed $file, string $algorithm = 'sha256', int $chunkSize = 8192): string;
}
