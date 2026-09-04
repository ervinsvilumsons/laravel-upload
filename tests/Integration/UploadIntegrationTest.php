<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Integration;

use ErvinsVilumsons\LaravelUpload\Upload\UploadSettings;
use ErvinsVilumsons\LaravelUpload\UploadManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('UploadManager Integration Tests', function (): void {
    describe('upload flow', function (): void {
        it('completes full upload flow', function (): void {
            $manager = UploadManager::profile('documents');
            $content = 'test document content';
            $file = UploadedFile::fake()->createWithContent('document.pdf', $content);

            $result = $manager->upload($file);

            // Verify file was stored
            expect(Storage::disk('test')->exists($result->path))->toBeTrue();

            // Verify metadata is complete
            expect($result->name)->not()->toBeEmpty();
            expect($result->path)->toContain('documents/');
            expect($result->url)->not()->toBeEmpty();
            expect($result->size)->toBe(strlen($content));
            expect($result->contentHash)->toBe(hash('sha256', $content));
        });

        it('stores multiple files independently', function (): void {
            $manager = UploadManager::profile('images');

            $file1 = UploadedFile::fake()->create('photo1.jpg', 1000);
            $file2 = UploadedFile::fake()->create('photo2.jpg', 2000);

            $result1 = $manager->upload($file1);
            $result2 = $manager->upload($file2);

            // Different files should have different paths
            expect($result1->path)->not()->toBe($result2->path);

            // Both should exist
            expect(Storage::disk('test')->exists($result1->path))->toBeTrue();
            expect(Storage::disk('test')->exists($result2->path))->toBeTrue();
        });

        it('enables content-based deduplication', function (): void {
            $manager = UploadManager::profile('documents');

            $content = 'same content';
            $file1 = UploadedFile::fake()->createWithContent('file1.txt', $content);
            $file2 = UploadedFile::fake()->createWithContent('file2.txt', $content);

            $result1 = $manager->upload($file1);
            $result2 = $manager->upload($file2);

            // Identical content should produce identical paths (deduplication)
            expect($result1->path)->toBe($result2->path);

            // File should only be stored once
            $files = Storage::disk('test')->allFiles();
            $matchingFiles = array_filter($files, fn ($f): bool => str_contains($f, $result1->name));
            expect(count($matchingFiles))->toBe(1);
        });
    });

    describe('multiple uploads in sequence', function (): void {
        it('handles multiple sequential uploads', function (): void {
            $manager = new UploadManager(UploadSettings::normalize(config('upload-manager.default', [])));

            $results = [];
            for ($i = 0; $i < 3; $i++) {
                $file = UploadedFile::fake()->create("file{$i}.txt", 100 * ($i + 1));
                $result = $manager->upload($file);
                $results[] = $result;
            }

            // All should be stored
            foreach ($results as $result) {
                expect(Storage::disk('test')->exists($result->path))->toBeTrue();
            }

            // All should have different paths (uuid strategy)
            $paths = array_map(fn ($r): string => $r->path, $results);
            expect(count($paths))->toBe(count(array_unique($paths)));
        });
    });

    describe('different profiles', function (): void {
        it('respects profile configurations', function (): void {
            $documentResult = UploadManager::profile('documents')->upload(
                UploadedFile::fake()->createWithContent('doc.txt', 'doc content')
            );

            $imageResult = UploadManager::profile('images')->upload(
                UploadedFile::fake()->create('image.jpg', 1000)
            );

            // Documents profile uses sha256 filename strategy
            expect($documentResult->name)->toMatch('/^[a-f0-9]{64}\.txt$/');

            // Images profile uses uuid strategy
            expect($imageResult->name)->toMatch('/^[0-9a-f-]+\.jpg$/i');

            // Different path configurations
            expect($documentResult->path)->toContain('documents/');
            expect($imageResult->path)->toContain('images/');
        });
    });

    describe('file retrieval', function (): void {
        it('can retrieve uploaded file content', function (): void {
            $manager = new UploadManager(UploadSettings::normalize(config('upload-manager.default', [])));
            $originalContent = 'test content for retrieval';
            $file = UploadedFile::fake()->createWithContent('test.txt', $originalContent);

            $result = $manager->upload($file);

            $storedContent = Storage::disk('test')->get($result->path);
            expect($storedContent)->toBe($originalContent);
        });

        it('can delete uploaded file', function (): void {
            $manager = new UploadManager(UploadSettings::normalize(config('upload-manager.default', [])));
            $file = UploadedFile::fake()->create('test.txt', 100);

            $result = $manager->upload($file);
            expect(Storage::disk('test')->exists($result->path))->toBeTrue();

            Storage::disk('test')->delete($result->path);
            expect(Storage::disk('test')->exists($result->path))->toBeFalse();
        });
    });

    describe('concurrent-like uploads', function (): void {
        it('handles rapid sequential uploads without conflicts', function (): void {
            $manager = UploadManager::profile('images');

            $results = [];
            for ($i = 0; $i < 5; $i++) {
                $file = UploadedFile::fake()->create("concurrent{$i}.jpg", 1000 + $i);
                $result = $manager->upload($file);
                $results[] = $result;
            }

            // All should be stored successfully
            foreach ($results as $result) {
                expect(Storage::disk('test')->exists($result->path))->toBeTrue();
            }

            // All paths should be unique (uuid strategy)
            $paths = array_map(fn ($r): string => $r->path, $results);
            expect(count(array_unique($paths)))->toBe(count($paths));
        });
    });

    describe('edge cases', function (): void {
        it('handles files with special characters in original name', function (): void {
            $manager = UploadManager::profile('documents');
            $file = UploadedFile::fake()->create('my-special_document (1).pdf', 500);

            $result = $manager->upload($file);

            expect($result->originalName)->toBe('my-special_document (1).pdf');
            expect(Storage::disk('test')->exists($result->path))->toBeTrue();
        });

        it('handles files with long names', function (): void {
            $manager = new UploadManager(UploadSettings::normalize(config('upload-manager.default', [])));
            $longName = str_repeat('a', 100).'.txt';
            $file = UploadedFile::fake()->create($longName, 100);

            $result = $manager->upload($file);

            expect($result->originalName)->toBe($longName);
        });

        it('handles files with multiple dots in name', function (): void {
            $manager = new UploadManager(UploadSettings::normalize(config('upload-manager.default', [])));
            $file = UploadedFile::fake()->create('my.backup.file.2026.01.15.txt', 100);

            $result = $manager->upload($file);

            expect($result->extension)->toBe('txt');
            expect($result->originalName)->toBe('my.backup.file.2026.01.15.txt');
        });
    });

    describe('storage disk operations', function (): void {
        it('uploaded file is accessible via storage disk', function (): void {
            $manager = new UploadManager(UploadSettings::normalize(config('upload-manager.default', [])));
            $content = 'accessible content';
            $file = UploadedFile::fake()->createWithContent('test.txt', $content);

            $result = $manager->upload($file);

            $disk = Storage::disk('test');
            expect($disk->exists($result->path))->toBeTrue();
            expect($disk->get($result->path))->toBe($content);
        });

        it('can list all uploaded files', function (): void {
            $manager = new UploadManager(UploadSettings::normalize(config('upload-manager.default', [])));

            UploadedFile::fake()->create('file1.txt', 100);
            UploadedFile::fake()->create('file2.txt', 100);
            UploadedFile::fake()->create('file3.txt', 100);

            $manager->upload(UploadedFile::fake()->create('file1.txt', 100));
            $manager->upload(UploadedFile::fake()->create('file2.txt', 100));

            $files = Storage::disk('test')->allFiles();
            expect(count($files))->toBeGreaterThanOrEqual(2);
        });
    });
});
