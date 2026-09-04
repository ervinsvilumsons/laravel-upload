<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Unit;

use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use ErvinsVilumsons\LaravelUpload\Upload\UploadFileMetadata;
use Illuminate\Http\UploadedFile;

describe('UploadFileMetadata', function (): void {
    it('reads metadata from file paths', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'metadata-');
        file_put_contents($path, 'metadata content');
        $metadata = new UploadFileMetadata;

        expect($metadata->originalName($path))->toBe(basename($path))
            ->and($metadata->extension($path))->toBe('')
            ->and($metadata->mimeType($path))->toBe('text/plain')
            ->and($metadata->size($path))->toBe(16);

        unlink($path);
    });

    it('reads metadata from uploaded files', function (): void {
        $metadata = new UploadFileMetadata;
        $file = UploadedFile::fake()->createWithContent('photo.jpg', 'image content');

        expect($metadata->originalName($file))->toBe('photo.jpg')
            ->and($metadata->extension($file))->toBe('jpg')
            ->and($metadata->mimeType($file))->toBeString()
            ->and($metadata->size($file))->toBe(13);
    });

    it('rejects uploaded files exceeding the upload limit', function (): void {
        $metadata = new UploadFileMetadata;
        $file = UploadedFile::fake()->create('too-large.bin', 1);

        $metadata->validateSize($file, 3 * 1024 * 1024);
    })->throws(UploadException::class, 'too large');

    it('rejects files with an ini upload error', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'upload-error-');
        file_put_contents($path, 'x');
        $file = new UploadedFile($path, 'failed.txt', null, UPLOAD_ERR_INI_SIZE, true);
        $metadata = new UploadFileMetadata;

        $metadata->validateSize($file, null);
    })->throws(UploadException::class, 'upload_max_filesize');
});
