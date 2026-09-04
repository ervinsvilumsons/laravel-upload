<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Encryption;

use ErvinsVilumsons\LaravelUpload\Contracts\EncryptionProviderContract;
use Illuminate\Http\UploadedFile;

/**
 * Authenticated streaming encryption provider.
 */
final class AesEncryptionProvider implements EncryptionProviderContract
{
    private const string MAGIC = 'LUMS1';

    /**
     * Encrypt a file stream using Laravel's encryption.
     *
     * @param  mixed  $file  The file to encrypt
     * @param  string  $outputPath  Path to write encrypted file
     * @param  int  $chunkSize  Size of chunks to process
     * @return bool True if successful
     */
    public function encrypt(mixed $file, string $outputPath, int $chunkSize = 8192): bool
    {
        $sourceFilename = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (! is_string($sourceFilename) || $sourceFilename === '') {
            return false;
        }

        try {
            $sourceStream = fopen($sourceFilename, 'rb');
            if (! $sourceStream) {
                return false;
            }

            $outputStream = fopen($outputPath, 'wb');
            if (! $outputStream) {
                fclose($sourceStream);

                return false;
            }

            try {
                return $this->encryptStream($sourceStream, $outputStream, $chunkSize);
            } finally {
                fclose($sourceStream);
                fclose($outputStream);
            }
        } catch (\Throwable) {
            return false;
        }
    }

    /** Encrypt a source resource into a destination resource using bounded memory. */
    public function encryptStream(mixed $sourceStream, mixed $outputStream, int $chunkSize = 8192, ?callable $progress = null, ?int $totalBytes = null): bool
    {
        if ($chunkSize < 1 || ! is_resource($sourceStream) || ! is_resource($outputStream)) {
            return false;
        }

        $state = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key());
        if (! isset($state[0], $state[1])) {
            return false;
        }

        $stateKey = $state[0];
        $stateHeader = $state[1];
        if (! is_string($stateKey) || ! is_string($stateHeader)) {
            return false;
        }

        $header = self::MAGIC.$stateHeader;
        if (fwrite($outputStream, $header) !== strlen($header)) {
            return false;
        }

        $buffer = '';
        $processed = 0;
        do {
            $needed = max(1, $chunkSize - strlen($buffer));
            $data = fread($sourceStream, $needed + 1);
            if ($data === false) {
                return false;
            }
            $buffer .= $data;
            $isFinal = strlen($data) <= $needed;
            $chunk = $isFinal ? $buffer : substr($buffer, 0, $chunkSize);
            $buffer = $isFinal ? '' : substr($buffer, $chunkSize);

            $tag = $isFinal
                ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $ciphertext = sodium_crypto_secretstream_xchacha20poly1305_push($stateKey, $chunk, '', $tag);

            if (! $this->writeFrame($outputStream, $ciphertext)) {
                return false;
            }
            $processed += strlen($chunk);
            if ($progress !== null) {
                $progress($processed, $totalBytes);
            }
        } while (! $isFinal);

        return true;
    }

    /**
     * Decrypt a file stream using Laravel's decryption.
     *
     * @param  string  $encryptedPath  Path to encrypted file
     * @param  string  $outputPath  Path to write decrypted file
     * @param  int  $chunkSize  Size of chunks to process
     * @return bool True if successful
     */
    public function decrypt(string $encryptedPath, string $outputPath, int $chunkSize = 8192): bool
    {
        try {
            $encryptedStream = fopen($encryptedPath, 'rb');
            if (! $encryptedStream) {
                return false;
            }

            $outputStream = fopen($outputPath, 'wb');
            if (! $outputStream) {
                fclose($encryptedStream);

                return false;
            }

            try {
                return $this->decryptStream($encryptedStream, $outputStream, $chunkSize);
            } finally {
                fclose($encryptedStream);
                fclose($outputStream);
            }
        } catch (\Throwable) {
            return false;
        }
    }

    /** Decrypt a framed stream using bounded memory. */
    public function decryptStream(mixed $sourceStream, mixed $outputStream, int $chunkSize = 8192): bool
    {
        if ($chunkSize < 1 || ! is_resource($sourceStream) || ! is_resource($outputStream)) {
            return false;
        }

        $headerLength = strlen(self::MAGIC) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
        $header = $this->readBytes($sourceStream, $headerLength);
        if (strlen($header) !== $headerLength || ! str_starts_with($header, self::MAGIC)) {
            return false;
        }

        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($header, strlen(self::MAGIC)), $this->key());
        $sawFinal = false;

        while (! $sawFinal) {
            $lengthBytes = $this->readBytes($sourceStream, 4);
            if (strlen($lengthBytes) !== 4) {
                return false;
            }

            $unpacked = unpack('Nlength', $lengthBytes);
            if (! is_array($unpacked) || ! array_key_exists('length', $unpacked) || ! is_int($unpacked['length'])) {
                return false;
            }

            $length = $unpacked['length'];
            if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES
                || $length > $chunkSize + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                return false;
            }
            $ciphertext = $this->readBytes($sourceStream, $length);
            if (strlen($ciphertext) !== $length) {
                return false;
            }

            $message = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $ciphertext);
            if ($message === false || ! is_string($message[0])) {
                return false;
            }

            if (fwrite($outputStream, $message[0]) !== strlen($message[0])) {
                return false;
            }
            $sawFinal = $message[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }

        return fread($sourceStream, 1) === '';
    }

    private function writeFrame(mixed $outputStream, string $ciphertext): bool
    {
        if (! is_resource($outputStream)) {
            return false;
        }

        return fwrite($outputStream, pack('N', strlen($ciphertext)).$ciphertext) === strlen($ciphertext) + 4;
    }

    private function readBytes(mixed $stream, int $length): string
    {
        if (! is_resource($stream)) {
            return '';
        }

        $contents = '';
        while (strlen($contents) < $length && ! feof($stream)) {
            $remaining = $length - strlen($contents);
            if ($remaining <= 0) {
                break;
            }

            $chunk = fread($stream, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $contents .= $chunk;
        }

        return $contents;
    }

    private function key(): string
    {
        $key = config('app.key');
        if (! is_string($key)) {
            $key = '';
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = is_string($decoded) ? $decoded : '';
        }

        return strlen($key) === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES
            ? $key
            : hash('sha256', $key, true);
    }
}
