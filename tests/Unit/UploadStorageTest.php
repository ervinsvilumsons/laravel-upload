<?php

declare(strict_types=1);

use ErvinsVilumsons\LaravelUpload\Encryption\AesEncryptionProvider;
use ErvinsVilumsons\LaravelUpload\Encryption\EncryptionFunctionMocks;
use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use ErvinsVilumsons\LaravelUpload\Generators\FilenameGenerator;
use ErvinsVilumsons\LaravelUpload\Upload\UploadFunctionMocks;
use ErvinsVilumsons\LaravelUpload\Upload\UploadStager;
use ErvinsVilumsons\LaravelUpload\Upload\UploadStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Mockery\MockInterface;

// ---------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------

function uploadStager(): UploadStager
{
    return new UploadStager(new FilenameGenerator);
}

/**
 * @return resource
 */
function uploadTmpStream(string $mode = 'w+')
{
    $handle = fopen('php://temp', $mode);
    if ($handle === false) {
        throw new RuntimeException('Unable to open temp stream');
    }

    return $handle;
}

/**
 * @return array{string, callable(): void}
 */
function uploadTempFile(string $contents = 'data'): array
{
    $path = (string) tempnam(sys_get_temp_dir(), 'src');
    file_put_contents($path, $contents);

    return [$path, static function () use ($path): void {
        @unlink($path);
    }];
}

function uploadDisk(): Filesystem&MockInterface
{
    /** @var Filesystem&MockInterface $mock */
    $mock = mock(Filesystem::class);

    return $mock;
}

function uploadStorage(Filesystem $disk, bool $encrypt): UploadStorage
{
    return new UploadStorage($disk, uploadStager(), new AesEncryptionProvider, $encrypt);
}

// ---------------------------------------------------------------------
// setup / teardown
// ---------------------------------------------------------------------

beforeEach(function (): void {
    EncryptionFunctionMocks::reset();
    UploadFunctionMocks::reset();

    $GLOBALS['app.key'] = 'base64:'.base64_encode(
        str_repeat('k', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES)
    );
});

afterEach(function (): void {
    EncryptionFunctionMocks::reset();
    UploadFunctionMocks::reset();
    unset($GLOBALS['app.key']);
});

// ---------------------------------------------------------------------
// store() — line 21
// ---------------------------------------------------------------------

it('store throws when source file cannot be opened', function (): void {
    $disk = uploadDisk();
    $disk->shouldNotReceive('put');

    $storage = uploadStorage($disk, false);

    expect(fn (): bool => $storage->store('/nonexistent/'.uniqid().'.txt', 'path', null, null))
        ->toThrow(UploadException::class, 'Unable to open file for upload.');
});

// ---------------------------------------------------------------------
// store() — line 29
// ---------------------------------------------------------------------

it('store throws when tmpfile fails in non-encrypted path', function (): void {
    UploadFunctionMocks::$tmpfile = static fn (): false => false;

    [$src, $cleanup] = uploadTempFile();

    $disk = uploadDisk();
    $disk->shouldNotReceive('put');

    $storage = uploadStorage($disk, false);

    try {
        expect(fn (): bool => $storage->store($src, 'path', null, null))
            ->toThrow(UploadException::class, 'Unable to create temporary stream for upload.');
    } finally {
        $cleanup();
    }
});

// ---------------------------------------------------------------------
// store() — line 39
// ---------------------------------------------------------------------

it('store throws when put fails in non-encrypted path', function (): void {
    [$src, $cleanup] = uploadTempFile();

    $disk = uploadDisk();
    $disk->shouldReceive('put')->once()->andReturn(false);

    $storage = uploadStorage($disk, false);

    try {
        expect(fn (): bool => $storage->store($src, 'path', null, null))
            ->toThrow(UploadException::class, 'Failed to upload file to storage.');
    } finally {
        $cleanup();
    }
});

// ---------------------------------------------------------------------
// readStream() — line 55
// ---------------------------------------------------------------------

it('readStream throws when disk readStream returns null', function (): void {
    $disk = uploadDisk();
    $disk->shouldReceive('readStream')->once()->with('path')->andReturn(null);

    $storage = uploadStorage($disk, false);

    expect(fn (): mixed => $storage->readStream('path'))
        ->toThrow(UploadException::class, 'Unable to open stored file for reading.');
});

// ---------------------------------------------------------------------
// readStream() — lines 62..63
// ---------------------------------------------------------------------

it('readStream throws when tmpfile fails for decryption', function (): void {
    UploadFunctionMocks::$tmpfile = static fn (): false => false;

    $stored = uploadTmpStream('r+');
    fwrite($stored, 'cipher');
    rewind($stored);

    $disk = uploadDisk();
    $disk->shouldReceive('readStream')->once()->with('path')->andReturn($stored);

    $storage = uploadStorage($disk, true);

    expect(fn (): mixed => $storage->readStream('path'))
        ->toThrow(UploadException::class, 'Unable to create temporary stream for decryption.');
});

// ---------------------------------------------------------------------
// readStream() — lines 67, 81
// ---------------------------------------------------------------------

it('readStream rethrows UploadException from decryption failure', function (): void {
    $stored = uploadTmpStream('r+');
    fwrite($stored, 'XXXXX');
    rewind($stored);

    $disk = uploadDisk();
    $disk->shouldReceive('readStream')->once()->with('path')->andReturn($stored);

    $storage = uploadStorage($disk, true);

    expect(fn (): mixed => $storage->readStream('path'))
        ->toThrow(UploadException::class, 'Unable to decrypt stored file.');
});

// ---------------------------------------------------------------------
// readStream() — line 83
// ---------------------------------------------------------------------

it('readStream wraps non-UploadException during decryption', function (): void {
    EncryptionFunctionMocks::$fread =
        static function ($stream, int $length): string|false {
            throw new RuntimeException('boom');
        };

    $stored = uploadTmpStream('r+');
    fwrite($stored, 'LUMS1'.str_repeat(
        'H',
        SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
    ));
    rewind($stored);

    $disk = uploadDisk();
    $disk->shouldReceive('readStream')->once()->with('path')->andReturn($stored);

    $storage = uploadStorage($disk, true);

    expect(fn (): mixed => $storage->readStream('path'))
        ->toThrow(UploadException::class, 'Failed to read encrypted file.');
});

// ---------------------------------------------------------------------
// encryptAndStore() — line 91
// ---------------------------------------------------------------------

it('encryptAndStore throws when tmpfile fails', function (): void {
    UploadFunctionMocks::$tmpfile = static fn (): false => false;

    [$src, $cleanup] = uploadTempFile();

    $storage = uploadStorage(uploadDisk(), true);

    try {
        expect(fn (): bool => $storage->store($src, 'path', null, null))
            ->toThrow(UploadException::class, 'Unable to create temporary stream for encryption.');
    } finally {
        $cleanup();
    }
});

// ---------------------------------------------------------------------
// encryptAndStore() — line 95
// ---------------------------------------------------------------------

it('encryptAndStore throws when encryption fails', function (): void {
    EncryptionFunctionMocks::$initPush = static fn (string $key): array => [];

    [$src, $cleanup] = uploadTempFile();

    $storage = uploadStorage(uploadDisk(), true);

    try {
        expect(fn (): bool => $storage->store($src, 'path', null, null))
            ->toThrow(UploadException::class);
    } finally {
        $cleanup();
    }
});

// ---------------------------------------------------------------------
// encryptAndStore() — line 98
// ---------------------------------------------------------------------

it('encryptAndStore throws when fseek fails', function (): void {
    UploadFunctionMocks::$fseek =
        static fn ($stream, int $offset, int $whence): int => -1;

    [$src, $cleanup] = uploadTempFile();

    $storage = uploadStorage(uploadDisk(), true);

    try {
        expect(fn (): bool => $storage->store($src, 'path', null, null))
            ->toThrow(UploadException::class, 'Unable to rewind encrypted stream.');
    } finally {
        $cleanup();
    }
});

// ---------------------------------------------------------------------
// encryptAndStore() — line 102
// ---------------------------------------------------------------------

it('encryptAndStore throws when put fails', function (): void {
    [$src, $cleanup] = uploadTempFile();

    $disk = uploadDisk();
    $disk->shouldReceive('put')->once()->andReturn(false);

    $storage = uploadStorage($disk, true);

    try {
        expect(fn (): bool => $storage->store($src, 'path', null, null))
            ->toThrow(UploadException::class, 'Failed to upload encrypted file to storage.');
    } finally {
        $cleanup();
    }
});

// ---------------------------------------------------------------------
// encryptAndStore() — line 110
// ---------------------------------------------------------------------

it('encryptAndStore wraps non-UploadException', function (): void {
    EncryptionFunctionMocks::$fwrite =
        static function ($stream, string $data, int $call): int|false {
            throw new RuntimeException('boom');
        };

    [$src, $cleanup] = uploadTempFile();

    $storage = uploadStorage(uploadDisk(), true);

    try {
        expect(fn (): bool => $storage->store($src, 'path', null, null))
            ->toThrow(UploadException::class, 'Failed to encrypt and upload file.');
    } finally {
        $cleanup();
    }
});
