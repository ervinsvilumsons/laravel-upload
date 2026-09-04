<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Unit;

use ErvinsVilumsons\LaravelUpload\Encryption\AesEncryptionProvider;

use function fclose;
use function fopen;
use function fwrite;
use function rewind;

describe('AesEncryptionProvider edge cases', function (): void {
    it('rejects invalid stream arguments and chunk sizes', function (): void {
        $provider = new AesEncryptionProvider;
        $stream = fopen('php://temp', 'w+b');

        if (! is_resource($stream)) {
            throw new \RuntimeException('Unable to create test stream.');
        }

        expect($provider->encryptStream(false, $stream, 0))->toBeFalse()
            ->and($provider->decryptStream(false, $stream, 0))->toBeFalse()
            ->and($provider->encryptStream($stream, false))->toBeFalse()
            ->and($provider->decryptStream($stream, false))->toBeFalse();

        fclose($stream);
    });

    it('encrypts and decrypts files through path wrappers', function (): void {
        config(['app.key' => 'test encryption key']);
        $provider = new AesEncryptionProvider;
        $source = tempnam(sys_get_temp_dir(), 'encrypt-source-');
        $encrypted = tempnam(sys_get_temp_dir(), 'encrypt-output-');
        $decrypted = tempnam(sys_get_temp_dir(), 'decrypt-output-');
        file_put_contents($source, 'file wrapper content');

        expect($provider->encrypt($source, $encrypted, 8))->toBeTrue()
            ->and($provider->decrypt($encrypted, $decrypted, 8))->toBeTrue()
            ->and(file_get_contents($decrypted))->toBe('file wrapper content')
            ->and($provider->encrypt(sys_get_temp_dir().'/missing-'.uniqid(), $encrypted))->toBeFalse()
            ->and($provider->encrypt($source, sys_get_temp_dir().'/missing-dir-'.uniqid().'/output'))->toBeFalse()
            ->and($provider->decrypt($encrypted, sys_get_temp_dir().'/missing-dir-'.uniqid().'/output'))->toBeFalse();

        unlink($source);
        unlink($encrypted);
        unlink($decrypted);
    });

    it('rejects malformed encrypted streams', function (): void {
        config(['app.key' => 'test encryption key']);
        $provider = new AesEncryptionProvider;
        $source = fopen('php://temp', 'w+b');
        $output = fopen('php://temp', 'w+b');
        if (! is_resource($source) || ! is_resource($output)) {
            throw new \RuntimeException('Unable to create test streams.');
        }
        fwrite($source, 'bad');
        rewind($source);

        expect($provider->decryptStream($source, $output))->toBeFalse();

        fclose($source);
        fclose($output);
    });
});
