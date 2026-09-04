<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Feature;

use ErvinsVilumsons\LaravelUpload\Facades\UploadManager as UploadManagerFacade;
use ErvinsVilumsons\LaravelUpload\UploadManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('UploadManager Facade', function (): void {
    it('allows uploading via facade', function (): void {
        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $result = UploadManagerFacade::profile('documents')->upload($file);

        expect($result->name)->not()->toBeEmpty();
        expect(Storage::disk('test')->exists($result->path))->toBeTrue();
    });

    it('facade profile method returns UploadManager instance', function (): void {
        $manager = UploadManagerFacade::profile('images');

        expect($manager)->toBeInstanceOf(UploadManager::class);
    });

    it('allows chaining upload calls via facade', function (): void {
        $file1 = UploadedFile::fake()->create('file1.txt', 100);
        $file2 = UploadedFile::fake()->create('file2.txt', 200);

        $result1 = UploadManagerFacade::profile('documents')->upload($file1);
        $result2 = UploadManagerFacade::profile('images')->upload($file2);

        expect($result1->path)->toContain('documents/');
        expect($result2->path)->toContain('images/');
    });

    it('facade provides convenient access to profiles', function (): void {
        foreach (['documents', 'images'] as $profile) {
            $manager = UploadManagerFacade::profile($profile);
            expect($manager)->toBeInstanceOf(UploadManager::class);
        }
    });
});
