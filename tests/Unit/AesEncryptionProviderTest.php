<?php

declare(strict_types=1);

use ErvinsVilumsons\LaravelUpload\Encryption\AesEncryptionProvider;
use ErvinsVilumsons\LaravelUpload\Encryption\EncryptionFunctionMocks;

// ---------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------

function provider(): AesEncryptionProvider
{
    return new AesEncryptionProvider;
}

/**
 * @return resource
 */
function stream(string $mode = 'w+')
{
    $handle = fopen('php://temp', $mode);
    if ($handle === false) {
        throw new RuntimeException('Unable to open temp stream');
    }

    return $handle;
}

/**
 * @return array{resource, resource}
 */
function memoryStreams(string $plain): array
{
    $src = stream('r+');
    fwrite($src, $plain);
    rewind($src);

    return [$src, stream('w+')];
}

/**
 * @return array{resource, resource}
 */
function encryptedHeaderOnlyStream(): array
{
    $header = 'LUMS1'.str_repeat(
        'H',
        SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
    );

    $src = stream('r+');
    fwrite($src, $header);
    rewind($src);

    return [$src, stream('w+')];
}

/**
 * @return array{resource, resource}
 */
function craftedEncryptedStream(string $payload): array
{
    $header = 'LUMS1'.str_repeat(
        'H',
        SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
    );

    $src = stream('r+');
    fwrite($src, $header.$payload);
    rewind($src);

    return [$src, stream('w+')];
}

/**
 * @return resource
 */
function makeEncryptedStream(string $plain)
{
    $src = stream('r+');
    fwrite($src, $plain);
    rewind($src);

    $dst = stream('w+');

    expect(provider()->encryptStream($src, $dst))->toBeTrue();

    fclose($src);
    rewind($dst);

    return $dst;
}

beforeEach(function () {
    EncryptionFunctionMocks::reset();

    $GLOBALS['app.key'] = 'base64:'.base64_encode(
        str_repeat('k', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES)
    );
});

afterEach(function () {
    EncryptionFunctionMocks::reset();
    unset($GLOBALS['app.key']);
});

// ---------------------------------------------------------------------
// sanity
// ---------------------------------------------------------------------

it('has the namespace mocks loaded', function () {
    expect(\function_exists(
        'ErvinsVilumsons\LaravelUpload\Encryption\config'
    ))->toBeTrue();
});

// ---------------------------------------------------------------------
// encrypt()
// ---------------------------------------------------------------------

it('encrypt returns false when source cannot be opened', function () {
    $out = tempnam(sys_get_temp_dir(), 'out');

    set_error_handler(static fn (): bool => true);
    try {
        expect(provider()->encrypt('/missing/'.uniqid(), (string) $out))->toBeFalse();
    } finally {
        restore_error_handler();
        @unlink((string) $out);
    }
});

it('encrypt returns false when output cannot be opened', function () {
    $src = (string) tempnam(sys_get_temp_dir(), 'src');
    file_put_contents($src, 'hello');

    $out = sys_get_temp_dir().'/missing-dir-'.uniqid().'/out.enc';

    set_error_handler(static fn (): bool => true);
    try {
        expect(provider()->encrypt($src, $out))->toBeFalse();
    } finally {
        restore_error_handler();
        @unlink($src);
    }
});

it('encrypt works end-to-end for a real file', function () {
    $src = (string) tempnam(sys_get_temp_dir(), 'src');
    file_put_contents($src, str_repeat('payload-', 2000));

    $enc = (string) tempnam(sys_get_temp_dir(), 'enc');
    $dec = (string) tempnam(sys_get_temp_dir(), 'dec');

    expect(provider()->encrypt($src, $enc))->toBeTrue();
    expect(provider()->decrypt($enc, $dec))->toBeTrue();

    expect(file_get_contents($dec))->toBe(file_get_contents($src));

    @unlink($src);
    @unlink($enc);
    @unlink($dec);
});

// ---------------------------------------------------------------------
// encryptStream()
// ---------------------------------------------------------------------

it('encryptStream returns false when chunk size is below 1', function () {
    [$src, $dst] = memoryStreams('data');

    expect(provider()->encryptStream($src, $dst, 0))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('encryptStream returns false when source is not a resource', function () {
    $dst = stream();

    expect(provider()->encryptStream('not-a-resource', $dst))->toBeFalse();

    fclose($dst);
});

it('encryptStream returns false when init_push state is empty', function () {
    EncryptionFunctionMocks::$initPush = static fn (string $key): array => [];

    [$src, $dst] = memoryStreams('data');

    expect(provider()->encryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('encryptStream returns false when init_push state types are invalid', function () {
    EncryptionFunctionMocks::$initPush = static fn (string $key): array => [123, 'header'];

    [$src, $dst] = memoryStreams('data');

    expect(provider()->encryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('encryptStream returns false when header write fails', function () {
    EncryptionFunctionMocks::$fwrite =
        static fn ($stream, string $data, int $call): int => 0;

    [$src, $dst] = memoryStreams('data');

    expect(provider()->encryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('encryptStream returns false when fread fails', function () {
    EncryptionFunctionMocks::$fread =
        static fn ($stream, int $length): false => false;

    [$src, $dst] = memoryStreams('data');

    expect(provider()->encryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('encryptStream returns false when frame write fails', function () {
    EncryptionFunctionMocks::$fwrite =
        static fn ($stream, string $data, int $call): int => $call === 1 ? strlen($data) : 0;

    [$src, $dst] = memoryStreams('data');

    expect(provider()->encryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('encryptStream calls progress callback', function () {
    /** @var list<array{int, int|null}> $calls */
    $calls = [];

    [$src, $dst] = memoryStreams(str_repeat('x', 100));

    $ok = provider()->encryptStream(
        $src,
        $dst,
        32,
        function (int $processed, ?int $total) use (&$calls): void {
            $calls[] = [$processed, $total];
        },
        100,
    );

    expect($ok)->toBeTrue();
    expect($calls)->not->toBeEmpty();

    $last = $calls[count($calls) - 1];
    expect($last[0])->toBe(100);
    expect($last[1])->toBe(100);

    fclose($src);
    fclose($dst);
});

// ---------------------------------------------------------------------
// decrypt()
// ---------------------------------------------------------------------

it('decrypt returns false when encrypted file cannot be opened', function () {
    $out = (string) tempnam(sys_get_temp_dir(), 'out');

    set_error_handler(static fn (): bool => true);
    try {
        expect(provider()->decrypt('/missing/'.uniqid(), $out))->toBeFalse();
    } finally {
        restore_error_handler();
        @unlink($out);
    }
});

it('decrypt returns false when output file cannot be opened', function () {
    $src = (string) tempnam(sys_get_temp_dir(), 'src');
    file_put_contents($src, 'dummy');

    $out = sys_get_temp_dir().'/missing-dir-'.uniqid().'/out.txt';

    set_error_handler(static fn (): bool => true);
    try {
        expect(provider()->decrypt($src, $out))->toBeFalse();
    } finally {
        restore_error_handler();
        @unlink($src);
    }
});

// ---------------------------------------------------------------------
// decryptStream()
// ---------------------------------------------------------------------

it('decryptStream returns false when chunk size is below 1', function () {
    [$src, $dst] = encryptedHeaderOnlyStream();

    expect(provider()->decryptStream($src, $dst, 0))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('decryptStream returns false when source is not a resource', function () {
    $dst = stream();

    expect(provider()->decryptStream('not-a-resource', $dst))->toBeFalse();

    fclose($dst);
});

it('decryptStream returns false when magic header is wrong', function () {
    $bad = str_repeat(
        'X',
        strlen('LUMS1') + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
    );

    $src = stream('r+');
    fwrite($src, $bad);
    rewind($src);

    $dst = stream();

    expect(provider()->decryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('decryptStream returns false when length bytes are missing', function () {
    [$src, $dst] = encryptedHeaderOnlyStream();

    expect(provider()->decryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('decryptStream returns false when unpack returns invalid data', function () {
    EncryptionFunctionMocks::$unpack =
        static fn (string $format, string $string): array => [];

    [$src, $dst] = craftedEncryptedStream(pack('N', 100));

    expect(provider()->decryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('decryptStream returns false when length is too small', function () {
    [$src, $dst] = craftedEncryptedStream(pack('N', 0));

    expect(provider()->decryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('decryptStream returns false when length is too large', function () {
    [$src, $dst] = craftedEncryptedStream(pack('N', 999999));

    expect(provider()->decryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('decryptStream returns false when ciphertext is short', function () {
    [$src, $dst] = craftedEncryptedStream(
        pack('N', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES).'x'
    );

    expect(provider()->decryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('decryptStream returns false when ciphertext fails authentication', function () {
    $header = 'LUMS1'.str_repeat(
        'H',
        SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
    );

    $length = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
    $payload = $header.pack('N', $length).str_repeat("\x00", $length);

    $src = stream('r+');
    fwrite($src, $payload);
    rewind($src);

    $dst = stream();

    expect(provider()->decryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('decryptStream returns false when output write fails', function () {
    $src = makeEncryptedStream('hello');
    $dst = stream();

    EncryptionFunctionMocks::$fwrite =
        static fn ($stream, string $data, int $call): int => 0;

    expect(provider()->decryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

it('decryptStream returns false when trailing bytes are present', function () {
    $src = makeEncryptedStream('hello');
    fwrite($src, 'trailing');
    rewind($src);

    $dst = stream();

    expect(provider()->decryptStream($src, $dst))->toBeFalse();

    fclose($src);
    fclose($dst);
});

// ---------------------------------------------------------------------
// private methods
// ---------------------------------------------------------------------

it('writeFrame returns false for non-resource', function () {
    $method = new ReflectionMethod(AesEncryptionProvider::class, 'writeFrame');
    $method->setAccessible(true);

    expect($method->invoke(provider(), 'not-a-resource', 'abc'))->toBeFalse();
});

it('readBytes returns empty for non-resource', function () {
    $method = new ReflectionMethod(AesEncryptionProvider::class, 'readBytes');
    $method->setAccessible(true);

    expect($method->invoke(provider(), 'not-a-resource', 10))->toBe('');
});

it('readBytes breaks when fread returns empty', function () {
    $method = new ReflectionMethod(AesEncryptionProvider::class, 'readBytes');
    $method->setAccessible(true);

    $src = stream('r+');
    fwrite($src, 'x');
    rewind($src);

    EncryptionFunctionMocks::$fread =
        static fn ($stream, int $length): string => '';

    expect($method->invoke(provider(), $src, 10))->toBe('');

    fclose($src);
});

// ---------------------------------------------------------------------
// key()
// ---------------------------------------------------------------------

it('key hashes non-sodium-length keys', function () {
    $GLOBALS['app.key'] = 'short';

    $method = new ReflectionMethod(AesEncryptionProvider::class, 'key');
    $method->setAccessible(true);

    expect($method->invoke(provider()))
        ->toBe(hash('sha256', 'short', true));
});

it('key handles non-string config', function () {
    $GLOBALS['app.key'] = null;

    $method = new ReflectionMethod(AesEncryptionProvider::class, 'key');
    $method->setAccessible(true);

    expect($method->invoke(provider()))
        ->toBe(hash('sha256', '', true));
});

it('key handles invalid base64', function () {
    $GLOBALS['app.key'] = 'base64:!!!not-valid!!!';

    $method = new ReflectionMethod(AesEncryptionProvider::class, 'key');
    $method->setAccessible(true);

    expect($method->invoke(provider()))
        ->toBe(hash('sha256', '', true));
});

it('key accepts a raw sodium-length key', function () {
    $raw = str_repeat('z', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
    $GLOBALS['app.key'] = $raw;

    $method = new ReflectionMethod(AesEncryptionProvider::class, 'key');
    $method->setAccessible(true);

    expect($method->invoke(provider()))->toBe($raw);
});
