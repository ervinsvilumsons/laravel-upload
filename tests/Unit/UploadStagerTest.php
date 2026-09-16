<?php

declare(strict_types=1);

use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use ErvinsVilumsons\LaravelUpload\Generators\FilenameGenerator;
use ErvinsVilumsons\LaravelUpload\Upload\UploadFunctionMocks;
use ErvinsVilumsons\LaravelUpload\Upload\UploadStager;

// ---------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------

function realStager(): UploadStager
{
    return new UploadStager(new FilenameGenerator);
}

function stagerWith(string $strategy): UploadStager
{
    return new UploadStager(new FilenameGenerator($strategy));
}

/**
 * @return resource
 */
function stagerStream(string $mode = 'w+')
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
function stagerTempFile(string $contents = 'data'): array
{
    $path = (string) tempnam(sys_get_temp_dir(), 'src');
    file_put_contents($path, $contents);

    return [$path, static function () use ($path): void {
        @unlink($path);
    }];
}

beforeEach(function () {
    UploadFunctionMocks::reset();
});

afterEach(function () {
    UploadFunctionMocks::reset();
});

// ---------------------------------------------------------------------
// stageAndHash() — lines 37..44
// ---------------------------------------------------------------------

it('stageAndHash cleans up and rethrows when copyStream throws', function () {
    [$src, $cleanup] = stagerTempFile();

    UploadFunctionMocks::$fread = static fn ($stream, int $length): false => false;

    try {
        expect(fn () => realStager()->stageAndHash($src, false, null, null))
            ->toThrow(UploadException::class, 'Unable to read file while copying.');
    } finally {
        $cleanup();
    }
});

// ---------------------------------------------------------------------
// stageAndHash() / cleanupStreams() — lines 76, 170
// ---------------------------------------------------------------------

it('stageAndHash throws when tempnam fails', function () {
    UploadFunctionMocks::$tempnam = static fn (string $dir, string $prefix): false => false;

    [$src, $cleanup] = stagerTempFile();

    try {
        expect(fn () => realStager()->stageAndHash($src, false, null, null))
            ->toThrow(UploadException::class);
    } finally {
        $cleanup();
    }
});

// ---------------------------------------------------------------------
// cleanupStreams() — lines 80, 84
// ---------------------------------------------------------------------

it('stageAndHash cleans up staged stream when source file cannot be opened', function () {
    expect(fn () => realStager()->stageAndHash(
        '/nonexistent/'.uniqid().'.txt',
        false,
        null,
        null,
    ))->toThrow(UploadException::class);
});

// ---------------------------------------------------------------------
// copyStream() — line 104
// ---------------------------------------------------------------------

it('copyStream throws when source is not a resource', function () {
    $dst = stagerStream('w+');

    try {
        expect(fn () => realStager()->copyStream(
            // @phpstan-ignore-next-line argument.type — deliberately testing the guard
            'not-a-resource',
            $dst,
            null,
            null,
        ))->toThrow(UploadException::class, 'Unable to copy file stream.');
    } finally {
        fclose($dst);
    }
});

// ---------------------------------------------------------------------
// createStagedStream() — lines 176..178
// ---------------------------------------------------------------------

it('stageAndHash throws when fopen for staged stream fails', function () {
    UploadFunctionMocks::$fopen = static function (string $filename, string $mode) {
        if ($mode === 'wb') {
            return false;
        }

        return \fopen($filename, $mode);
    };

    [$src, $cleanup] = stagerTempFile();

    try {
        expect(fn () => realStager()->stageAndHash($src, false, null, null))
            ->toThrow(UploadException::class);
    } finally {
        $cleanup();
    }
});

// ---------------------------------------------------------------------
// copyStream() — line 117
// ---------------------------------------------------------------------

it('copyStream throws when fread returns false', function () {
    $src = stagerStream('r+');
    fwrite($src, 'data');
    rewind($src);

    $dst = stagerStream('w+');

    UploadFunctionMocks::$fread = static fn ($stream, int $length): false => false;

    expect(fn () => realStager()->copyStream($src, $dst, null, null))
        ->toThrow(UploadException::class, 'Unable to read file while copying.');

    fclose($src);
    fclose($dst);
});

// ---------------------------------------------------------------------
// copyStream() — line 127
// ---------------------------------------------------------------------

it('copyStream throws when fwrite fails', function () {
    $src = stagerStream('r+');
    fwrite($src, 'data');
    rewind($src);

    $dst = stagerStream('w+');

    UploadFunctionMocks::$fwrite = static fn ($stream, string $data, ?int $length): int => 0;

    expect(fn () => realStager()->copyStream($src, $dst, null, null))
        ->toThrow(UploadException::class, 'Unable to write file while copying.');

    fclose($src);
    fclose($dst);
});
