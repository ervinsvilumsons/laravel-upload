<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Upload;

use Closure;

final class UploadFunctionMocks
{
    /** @var (Closure(): (resource|false))|null */
    public static ?Closure $tmpfile = null;

    /** @var (Closure(resource, int, int): int)|null */
    public static ?Closure $fseek = null;

    /** @var (Closure(string, string): (string|false))|null */
    public static ?Closure $tempnam = null;

    /** @var (Closure(string, string): (resource|false))|null */
    public static ?Closure $fopen = null;

    /** @var (Closure(resource, int): (string|false))|null */
    public static ?Closure $fread = null;

    /** @var (Closure(resource, string, ?int): (int|false))|null */
    public static ?Closure $fwrite = null;

    public static function reset(): void
    {
        self::$tmpfile = null;
        self::$fseek = null;
        self::$tempnam = null;
        self::$fopen = null;
        self::$fread = null;
        self::$fwrite = null;
    }
}

/** @return resource|false */
function tmpfile()
{
    if (UploadFunctionMocks::$tmpfile !== null) {
        return (UploadFunctionMocks::$tmpfile)();
    }

    return \tmpfile();
}

/** @param resource $stream */
function fseek($stream, int $offset, int $whence = SEEK_SET): int
{
    if (UploadFunctionMocks::$fseek !== null) {
        return (UploadFunctionMocks::$fseek)($stream, $offset, $whence);
    }

    return \fseek($stream, $offset, $whence);
}

function tempnam(string $directory, string $prefix): string|false
{
    if (UploadFunctionMocks::$tempnam !== null) {
        return (UploadFunctionMocks::$tempnam)($directory, $prefix);
    }

    return \tempnam($directory, $prefix);
}

/** @return resource|false */
function fopen(string $filename, string $mode)
{
    if (UploadFunctionMocks::$fopen !== null) {
        return (UploadFunctionMocks::$fopen)($filename, $mode);
    }

    return \fopen($filename, $mode);
}

/** @param resource $stream */
function fread($stream, int $length): string|false
{
    if (UploadFunctionMocks::$fread !== null) {
        return (UploadFunctionMocks::$fread)($stream, $length);
    }

    if ($length < 1) {
        return false;
    }

    return \fread($stream, $length);
}

/** @param resource $stream */
function fwrite($stream, string $data, ?int $length = null): int|false
{
    if (UploadFunctionMocks::$fwrite !== null) {
        return (UploadFunctionMocks::$fwrite)($stream, $data, $length);
    }

    if ($length === null) {
        return \fwrite($stream, $data);
    }

    if ($length < 0) {
        return false;
    }

    return \fwrite($stream, $data, $length);
}
