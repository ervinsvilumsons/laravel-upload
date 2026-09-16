<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Encryption;

use Closure;

final class EncryptionFunctionMocks
{
    /** @var (Closure(string): array<int, mixed>)|null */
    public static ?Closure $initPush = null;

    /** @var (Closure(resource, int): (string|false))|null */
    public static ?Closure $fread = null;

    /** @var (Closure(resource, string, int): (int|false))|null */
    public static ?Closure $fwrite = null;

    /** @var (Closure(string, string): (array<string, mixed>|false))|null */
    public static ?Closure $unpack = null;

    public static int $fwriteCalls = 0;

    public static function reset(): void
    {
        self::$initPush = null;
        self::$fread = null;
        self::$fwrite = null;
        self::$unpack = null;
        self::$fwriteCalls = 0;
    }
}

function config(string $key): mixed
{
    if (array_key_exists('app.key', $GLOBALS)) {
        return $GLOBALS['app.key'];
    }

    return 'base64:'.base64_encode(str_repeat('a', 32));
}

/**
 * @param  resource  $stream
 */
function fread($stream, int $length): string|false
{
    if (EncryptionFunctionMocks::$fread !== null) {
        return (EncryptionFunctionMocks::$fread)($stream, $length);
    }

    if ($length < 1) {
        return false;
    }

    return \fread($stream, $length);
}

/**
 * @param  resource  $stream
 */
function fwrite($stream, string $data, ?int $length = null): int|false
{
    EncryptionFunctionMocks::$fwriteCalls++;

    if (EncryptionFunctionMocks::$fwrite !== null) {
        return (EncryptionFunctionMocks::$fwrite)(
            $stream,
            $data,
            EncryptionFunctionMocks::$fwriteCalls
        );
    }

    if ($length === null) {
        return \fwrite($stream, $data);
    }

    if ($length < 0) {
        return false;
    }

    return \fwrite($stream, $data, $length);
}

/**
 * @return array<string, mixed>|false
 */
function unpack(string $format, string $string, int $offset = 0): array|false
{
    if (EncryptionFunctionMocks::$unpack !== null) {
        return (EncryptionFunctionMocks::$unpack)($format, $string);
    }

    /** @var array<string, mixed>|false $result */
    $result = \unpack($format, $string, $offset);

    return $result;
}

/**
 * @return array<int, mixed>
 */
function sodium_crypto_secretstream_xchacha20poly1305_init_push(string $key): array
{
    if (EncryptionFunctionMocks::$initPush !== null) {
        return (EncryptionFunctionMocks::$initPush)($key);
    }

    /** @var array<int, mixed> $result */
    $result = \sodium_crypto_secretstream_xchacha20poly1305_init_push($key);

    return $result;
}
