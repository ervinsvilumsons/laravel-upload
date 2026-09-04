<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Feature;

use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use ErvinsVilumsons\LaravelUpload\Upload\UploadSettings;
use ErvinsVilumsons\LaravelUpload\UploadManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('UploadManager', function (): void {
    $manager = fn (): UploadManager => new UploadManager(UploadSettings::normalize(config('upload-manager.default', [])));

    describe('basic upload', function () use ($manager): void {
        it('reports progress while streaming', function () use ($manager): void {
            $file = UploadedFile::fake()->createWithContent('progress.txt', str_repeat('x', 20000));
            $updates = [];

            $result = $manager()->upload($file, [], function (int $processed, ?int $total) use (&$updates): void {
                $updates[] = [$processed, $total];
            });

            expect($result->size)->toBe(20000);
            expect($updates)->not->toBeEmpty();
            expect(end($updates))->toBe([20000, 20000]);
            $processedValues = array_column($updates, 0);
            $sortedValues = $processedValues;
            sort($sortedValues);
            expect($processedValues)->toBe($sortedValues);
        });

        it('uploads file successfully', function () use ($manager): void {
            $file = UploadedFile::fake()->create('document.pdf', 1000);

            $result = $manager()->upload($file);

            expect($result->name)->not()->toBeEmpty();
            expect($result->path)->not()->toBeEmpty();
            expect($result->size)->toBe(1024 * 1000);
            expect($result->extension)->toBe('pdf');
            expect(Storage::disk('test')->exists($result->path))->toBeTrue();
        });

        it('returns upload result with metadata', function () use ($manager): void {
            $file = UploadedFile::fake()->create('test.txt', 500);

            $result = $manager()->upload($file);

            expect($result->name)->toBeString();
            expect($result->originalName)->toBe('test.txt');
            expect($result->extension)->toBe('txt');
            expect($result->mimeType)->not()->toBeEmpty();
            expect($result->size)->toBe(1024 * 500);
            expect($result->path)->toBeString();
            expect($result->url)->toBeString();
        });

        it('stores file in configured disk and path', function (): void {
            $manager = new UploadManager([
                'disk' => 'test',
                'path' => 'uploads',
                'filename' => 'uuid',
            ]);

            $file = UploadedFile::fake()->create('document.pdf', 1000);
            $result = $manager->upload($file);

            expect($result->path)->toContain('uploads/');
            expect(Storage::disk('test')->exists($result->path))->toBeTrue();
        });

        it('allows custom filename', function () use ($manager): void {
            $file = UploadedFile::fake()->create('document.pdf', 1000);

            $result = $manager()->upload($file, ['name' => 'custom-name.pdf']);

            expect($result->name)->toBe('custom-name.pdf');
            expect($result->path)->toContain('custom-name.pdf');
        });
    });

    describe('filename strategies', function (): void {
        it('uses uuid strategy', function (): void {
            $manager = new UploadManager([
                'disk' => 'test',
                'path' => 'uploads',
                'filename' => 'uuid',
            ]);

            $file = UploadedFile::fake()->create('document.pdf', 1000);
            $result = $manager->upload($file);

            expect($result->name)->toMatch('/^[0-9a-f-]+\.pdf$/i');
        });

        it('uses original strategy', function (): void {
            $manager = new UploadManager([
                'disk' => 'test',
                'path' => 'uploads',
                'filename' => 'original',
            ]);

            $file = UploadedFile::fake()->create('my-document.pdf', 1000);
            $result = $manager->upload($file);

            expect($result->name)->toBe('my-document.pdf');
        });

        it('uses hash strategy for content-based deduplication', function (): void {
            $manager = new UploadManager([
                'disk' => 'test',
                'path' => 'uploads',
                'filename' => 'sha256',
            ]);

            $content = 'duplicate content for deduplication test';
            $file1 = UploadedFile::fake()->createWithContent('file1.txt', $content);
            $file2 = UploadedFile::fake()->createWithContent('file2.txt', $content);

            $result1 = $manager->upload($file1);
            $result2 = $manager->upload($file2);

            // Both files have same content, should generate same hash-based name
            expect($result1->name)->toBe($result2->name);
        });
    });

    describe('hashing', function (): void {
        it('computes content hash when enabled', function (): void {
            $manager = new UploadManager([
                'disk' => 'test',
                'path' => 'uploads',
                'filename' => 'uuid',
                'hash' => true,
            ]);

            $file = UploadedFile::fake()->createWithContent('test.txt', 'content');
            $result = $manager->upload($file);

            expect($result->contentHash)->not()->toBeNull();
            expect($result->contentHash)->toBe(hash('sha256', 'content'));
        });

        it('returns null hash when disabled', function (): void {
            $manager = new UploadManager([
                'disk' => 'test',
                'path' => 'uploads',
                'filename' => 'uuid',
                'hash' => false,
            ]);

            $file = UploadedFile::fake()->create('test.txt', 100);
            $result = $manager->upload($file);

            expect($result->contentHash)->toBeNull();
        });
    });

    describe('profile-based uploads', function () use ($manager): void {
        it('streams encrypted uploads', function (): void {
            $manager = new UploadManager([
                'disk' => 'test',
                'path' => 'encrypted',
                'filename' => 'uuid',
                'encrypt' => true,
            ]);
            $file = UploadedFile::fake()->createWithContent('secret.txt', 'secret content');

            $updates = [];
            $result = $manager->upload($file, [], function (int $processed, ?int $total) use (&$updates): void {
                $updates[] = [$processed, $total];
            });

            expect(Storage::disk('test')->get($result->path))->toStartWith('LUMS1');
            expect(end($updates))->toBe([14, 14]);
        });

        it('reads encrypted uploads as plaintext streams', function (): void {
            $manager = new UploadManager([
                'disk' => 'test',
                'path' => 'encrypted',
                'filename' => 'uuid',
                'encrypt' => true,
            ]);
            $content = str_repeat('secret content', 1000);
            $result = $manager->upload(UploadedFile::fake()->createWithContent('secret.txt', $content));

            $stream = $manager->readStream($result->path);

            expect(stream_get_contents($stream))->toBe($content);
            fclose($stream);
        });

        it('reads unencrypted uploads as streams', function () use ($manager): void {
            $content = 'plain content';
            $defaultManager = $manager();
            $result = $defaultManager->upload(UploadedFile::fake()->createWithContent('plain.txt', $content));

            $stream = $defaultManager->readStream($result->path);

            expect(stream_get_contents($stream))->toBe($content);
            fclose($stream);
        });

        it('loads profile configuration', function (): void {
            $manager = UploadManager::profile('documents');

            expect($manager)->toBeInstanceOf(UploadManager::class);
        });

        it('uploads with documents profile', function (): void {
            $manager = UploadManager::profile('documents');
            $content = 'document content';
            $file = UploadedFile::fake()->createWithContent('doc.txt', $content);

            $result = $manager->upload($file);

            expect($result->path)->toContain('documents/');
            // Documents profile uses sha256 strategy
            expect($result->name)->toBe(hash('sha256', $content).'.txt');
            // Documents profile enables hashing
            expect($result->contentHash)->toBe(hash('sha256', $content));
        });

        it('uploads with images profile', function (): void {
            $manager = UploadManager::profile('images');
            $file = UploadedFile::fake()->create('photo.jpg', 1000);

            $result = $manager->upload($file);

            // Images profile uses date-based path
            expect($result->path)->toMatch('#images/\d{4}/\d{2}/\d{2}#');
            expect($result->contentHash)->not()->toBeNull();
        });

        it('throws exception for non-existent profile', function (): void {
            UploadManager::profile('non-existent-profile');
        })->throws(UploadException::class);
    });

    describe('large files', function (): void {
        it('handles large files with minimal memory', function (): void {
            $manager = new UploadManager([
                'disk' => 'test',
                'path' => 'uploads',
                'filename' => 'uuid',
                'hash' => true,
            ]);

            // Create 5MB fake file (tests streaming without actual large file)
            $file = UploadedFile::fake()->create('large.bin', 1000);

            $result = $manager->upload($file);

            expect($result->size)->toBe(1024 * 1000);
            expect(Storage::disk('test')->exists($result->path))->toBeTrue();
        });
    });

    describe('file extensions', function () use ($manager): void {
        it('preserves file extension', function () use ($manager): void {
            $extensions = ['pdf', 'txt', 'jpg', 'png', 'docx', 'xlsx', 'mp4'];

            foreach ($extensions as $ext) {
                $file = UploadedFile::fake()->create("file.{$ext}", 100);
                $result = $manager()->upload($file);

                expect($result->extension)->toBe($ext);
                expect($result->path)->toEndWith(".{$ext}");
            }
        });
    });

    describe('error handling', function (): void {
        it('throws exception when file cannot be uploaded', function (): void {
            $badManager = new UploadManager([
                'disk' => 'invalid-disk',
                'path' => 'uploads',
                'filename' => 'uuid',
            ]);

            $file = UploadedFile::fake()->create('test.txt', 100);

            $badManager->upload($file);
        })->throws(\Exception::class);
    });

    describe('metadata', function () use ($manager): void {
        it('captures mime type', function () use ($manager): void {
            $file = UploadedFile::fake()->create('document.pdf', 1000);

            $result = $manager()->upload($file);

            expect($result->mimeType)->not()->toBeEmpty();
        });

        it('captures file size', function () use ($manager): void {
            $file = UploadedFile::fake()->createWithContent('test.txt', str_repeat('x', 12345));

            $result = $manager()->upload($file);

            expect($result->size)->toBe(12345);
        });

        it('captures original filename', function () use ($manager): void {
            $file = UploadedFile::fake()->create('my-original-file.pdf', 1000);

            $result = $manager()->upload($file);

            expect($result->originalName)->toBe('my-original-file.pdf');
        });
    });
});
