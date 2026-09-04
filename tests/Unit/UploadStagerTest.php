<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Unit;

use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use ErvinsVilumsons\LaravelUpload\Generators\FilenameGenerator;
use ErvinsVilumsons\LaravelUpload\Upload\UploadStager;

use function fclose;
use function fopen;
use function fwrite;
use function rewind;
use function stream_get_contents;

describe('UploadStager', function (): void {
    it('copies streams with progress and optional hashes', function (): void {
        $stager = new UploadStager(new FilenameGenerator('sha256'));
        $source = fopen('php://temp', 'w+b');
        $destination = fopen('php://temp', 'w+b');
        if (! is_resource($source) || ! is_resource($destination)) {
            throw new \RuntimeException('Unable to create test streams.');
        }
        $hashers = ['sha256' => hash_init('sha256')];
        $updates = [];
        fwrite($source, 'streamed content');
        rewind($source);

        $processed = $stager->copyStream($source, $destination, function (int $bytes, ?int $total) use (&$updates): void {
            $updates[] = [$bytes, $total];
        }, 16, $hashers);

        rewind($destination);
        expect($processed)->toBe(16)
            ->and(stream_get_contents($destination))->toBe('streamed content')
            ->and(hash_final($hashers['sha256']))->toBe(hash('sha256', 'streamed content'))
            ->and($updates)->toBe([[0, 16], [16, 16]]);

        fclose($source);
        fclose($destination);
    });

    it('copies streams without hashers', function (): void {
        $stager = new UploadStager(new FilenameGenerator('original'));
        $source = fopen('php://temp', 'w+b');
        $destination = fopen('php://temp', 'w+b');
        if (! is_resource($source) || ! is_resource($destination)) {
            throw new \RuntimeException('Unable to create test streams.');
        }
        fwrite($source, 'plain');
        rewind($source);

        expect($stager->copyStream($source, $destination, null, null))->toBe(5);

        fclose($source);
        fclose($destination);
    });

    it('rejects invalid stream handles', function (): void {
        $stager = new UploadStager(new FilenameGenerator('original'));

        // @phpstan-ignore-next-line argument.type
        $stager->copyStream(false, false, null, null);
    })->throws(UploadException::class, 'copy file stream');

    it('opens existing files and rejects missing files', function (): void {
        $stager = new UploadStager(new FilenameGenerator('original'));
        $path = tempnam(sys_get_temp_dir(), 'stager-');
        file_put_contents($path, 'content');

        $stream = $stager->openFileStream($path);
        if (! is_resource($stream)) {
            throw new \RuntimeException('Unable to open test stream.');
        }
        fclose($stream);
        unlink($path);

        expect($stager->openFileStream($path))->toBeFalse();
    });

    it('stages and hashes a file', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'stager-source-');
        file_put_contents($path, 'hashed content');
        $stager = new UploadStager(new FilenameGenerator('sha256'));

        [$stagedPath, $filenameHash, $contentHash] = $stager->stageAndHash($path, true, null, 14);

        expect(file_get_contents($stagedPath))->toBe('hashed content')
            ->and($filenameHash)->toBe(hash('sha256', 'hashed content'))
            ->and($contentHash)->toBe(hash('sha256', 'hashed content'));

        unlink($path);
        unlink($stagedPath);
    });

    it('rejects staging a missing source file', function (): void {
        $stager = new UploadStager(new FilenameGenerator('original'));

        $stager->stageAndHash(sys_get_temp_dir().'/missing-stage-'.uniqid(), false, null, null);
    })->throws(UploadException::class, 'stage file');
});
