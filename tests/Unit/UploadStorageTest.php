<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Unit;

use ErvinsVilumsons\LaravelUpload\Encryption\AesEncryptionProvider;
use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use ErvinsVilumsons\LaravelUpload\Generators\FilenameGenerator;
use ErvinsVilumsons\LaravelUpload\Upload\UploadStager;
use ErvinsVilumsons\LaravelUpload\Upload\UploadStorage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;

use function fclose;
use function stream_get_contents;

describe('UploadStorage', function (): void {
    it('stores and reads an unencrypted file', function (): void {
        $disk = Storage::disk('test');
        $storage = new UploadStorage($disk, new UploadStager(new FilenameGenerator('original')), new AesEncryptionProvider, false);
        $sourcePath = tempnam(sys_get_temp_dir(), 'storage-');
        file_put_contents($sourcePath, 'stored content');

        expect($storage->store($sourcePath, 'unit/plain.txt', null, 14))->toBeTrue();
        $stream = $storage->readStream('unit/plain.txt');

        expect(stream_get_contents($stream))->toBe('stored content');
        fclose($stream);
        unlink($sourcePath);
    });

    it('rejects a missing source file', function (): void {
        $storage = new UploadStorage(Storage::disk('test'), new UploadStager(new FilenameGenerator('original')), new AesEncryptionProvider, false);

        $storage->store(sys_get_temp_dir().'/missing-source-'.uniqid(), 'missing.txt', null, null);
    })->throws(UploadException::class, 'open file');

    it('rejects a missing stored file', function (): void {
        $storage = new UploadStorage(Storage::disk('test'), new UploadStager(new FilenameGenerator('original')), new AesEncryptionProvider, false);

        $storage->readStream('missing-stored-file.txt');
    })->throws(UploadException::class, 'stored file');

    it('rejects invalid encrypted stored content', function (): void {
        Storage::disk('test')->put('invalid-encrypted.bin', 'not encrypted content');
        $storage = new UploadStorage(Storage::disk('test'), new UploadStager(new FilenameGenerator('original')), new AesEncryptionProvider, true);

        $storage->readStream('invalid-encrypted.bin');
    })->throws(UploadException::class, 'decrypt');

    it('rejects a failed storage write', function (): void {
        /** @var FilesystemAdapter&MockInterface $disk */
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $sourcePath = tempnam(sys_get_temp_dir(), 'storage-failed-');
        file_put_contents($sourcePath, 'failed write');
        $storage = new UploadStorage($disk, new UploadStager(new FilenameGenerator('original')), new AesEncryptionProvider, false);

        $storage->store($sourcePath, 'failed.txt', null, 12);
    })->throws(UploadException::class, 'Failed to upload');

    it('rejects a failed encrypted storage write', function (): void {
        /** @var FilesystemAdapter&MockInterface $disk */
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $sourcePath = tempnam(sys_get_temp_dir(), 'storage-encrypted-failed-');
        file_put_contents($sourcePath, 'failed encrypted write');
        $storage = new UploadStorage($disk, new UploadStager(new FilenameGenerator('original')), new AesEncryptionProvider, true);

        $storage->store($sourcePath, 'failed-encrypted.txt', null, 22);
    })->throws(UploadException::class, 'Failed to upload encrypted');

});
