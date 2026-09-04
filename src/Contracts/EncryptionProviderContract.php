<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Contracts;

/**
 * Stream-aware encryption provider for encrypting uploaded files.
 * Can be implemented by various encryption strategies (AES, RSA, etc.)
 */
interface EncryptionProviderContract
{
    /**
     * Encrypt a file stream.
     *
     * @param  mixed  $file  The file to encrypt (UploadedFile or path string)
     * @param  string  $outputPath  The path where encrypted file should be stored
     * @param  int  $chunkSize  The size of chunks to read and encrypt
     * @return bool True if encryption was successful
     */
    public function encrypt(mixed $file, string $outputPath, int $chunkSize = 8192): bool;

    /**
     * Decrypt a file stream.
     *
     * @param  string  $encryptedPath  The path to the encrypted file
     * @param  string  $outputPath  The path where decrypted file should be stored
     * @param  int  $chunkSize  The size of chunks to read and decrypt
     * @return bool True if decryption was successful
     */
    public function decrypt(string $encryptedPath, string $outputPath, int $chunkSize = 8192): bool;
}
