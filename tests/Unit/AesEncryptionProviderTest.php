<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Unit;

use ErvinsVilumsons\LaravelUpload\Encryption\AesEncryptionProvider;

describe('AesEncryptionProvider', function (): void {
    it('round trips empty, exact-size, and multi-chunk streams', function (): void {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $provider = new AesEncryptionProvider;

        foreach (['', str_repeat('a', 32), str_repeat('b', 65)] as $content) {
            $source = fopen('php://temp', 'w+b');
            $encrypted = fopen('php://temp', 'w+b');
            $decrypted = fopen('php://temp', 'w+b');
            if (! is_resource($source) || ! is_resource($encrypted) || ! is_resource($decrypted)) {
                throw new \RuntimeException('Unable to create temp streams for encryption test.');
            }
            fwrite($source, $content);
            rewind($source);

            expect($provider->encryptStream($source, $encrypted, 32))->toBeTrue();
            rewind($encrypted);
            expect($provider->decryptStream($encrypted, $decrypted, 32))->toBeTrue();
            rewind($decrypted);
            expect(stream_get_contents($decrypted))->toBe($content);

            fclose($source);
            fclose($encrypted);
            fclose($decrypted);
        }
    });

    it('rejects tampered ciphertext', function (): void {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $provider = new AesEncryptionProvider;
        $source = fopen('php://temp', 'w+b');
        $encrypted = fopen('php://temp', 'w+b');
        $decrypted = fopen('php://temp', 'w+b');
        if (! is_resource($source) || ! is_resource($encrypted) || ! is_resource($decrypted)) {
            throw new \RuntimeException('Unable to create temp streams for encryption test.');
        }
        fwrite($source, str_repeat('content', 20));
        rewind($source);

        expect($provider->encryptStream($source, $encrypted, 16))->toBeTrue();
        fseek($encrypted, -1, SEEK_END);
        fwrite($encrypted, 'x');
        rewind($encrypted);

        expect($provider->decryptStream($encrypted, $decrypted, 16))->toBeFalse();

        fclose($source);
        fclose($encrypted);
        fclose($decrypted);
    });
});
