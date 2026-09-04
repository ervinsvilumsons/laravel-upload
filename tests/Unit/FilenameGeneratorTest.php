<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Unit;

use ErvinsVilumsons\LaravelUpload\Generators\FilenameGenerator;
use Illuminate\Http\UploadedFile;

describe('FilenameGenerator', function (): void {
    describe('uuid strategy', function (): void {
        it('generates uuid filenames', function (): void {
            $generator = new FilenameGenerator('uuid');
            $file = UploadedFile::fake()->create('document.pdf', 1000);

            $filename = $generator->resolve($file);

            expect($filename)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$/i');
        });

        it('preserves file extension', function (): void {
            $generator = new FilenameGenerator('uuid');

            foreach (['pdf', 'txt', 'jpg', 'png', 'docx'] as $ext) {
                $file = UploadedFile::fake()->create("file.{$ext}", 100);
                $filename = $generator->resolve($file);

                expect($filename)->toEndWith(".{$ext}");
            }
        });

        it('generates different uuids for each call', function (): void {
            $generator = new FilenameGenerator('uuid');
            $file = UploadedFile::fake()->create('document.pdf', 1000);

            $filename1 = $generator->resolve($file);
            $filename2 = $generator->resolve($file);

            expect($filename1)->not()->toBe($filename2);
        });
    });

    describe('original strategy', function (): void {
        it('keeps original filename', function (): void {
            $generator = new FilenameGenerator('original');
            $file = UploadedFile::fake()->create('my-document.pdf', 1000);

            $filename = $generator->resolve($file);

            expect($filename)->toBe('my-document.pdf');
        });

        it('works with file paths', function (): void {
            $generator = new FilenameGenerator('original');

            $filename = $generator->resolve('/path/to/my-file.txt');

            expect($filename)->toBe('my-file.txt');
        });
    });

    describe('hash-based strategies', function (): void {
        it('generates sha256-based filename', function (): void {
            $generator = new FilenameGenerator('sha256');
            $content = 'file content for sha256';
            $file = UploadedFile::fake()->createWithContent('test.txt', $content);

            $filename = $generator->resolve($file);

            $expectedHash = hash('sha256', $content);
            expect($filename)->toBe("{$expectedHash}.txt");
        });

        it('generates md5-based filename', function (): void {
            $generator = new FilenameGenerator('md5');
            $content = 'content for md5';
            $file = UploadedFile::fake()->createWithContent('test.txt', $content);

            $filename = $generator->resolve($file);

            $expectedHash = hash('md5', $content);
            expect($filename)->toBe("{$expectedHash}.txt");
        });

        it('generates sha1-based filename', function (): void {
            $generator = new FilenameGenerator('sha1');
            $content = 'content for sha1';
            $file = UploadedFile::fake()->createWithContent('test.txt', $content);

            $filename = $generator->resolve($file);

            $expectedHash = hash('sha1', $content);
            expect($filename)->toBe("{$expectedHash}.txt");
        });

        it('generates same filename for identical content', function (): void {
            $generator = new FilenameGenerator('sha256');
            $content = 'identical content';

            $file1 = UploadedFile::fake()->createWithContent('test1.txt', $content);
            $file2 = UploadedFile::fake()->createWithContent('test2.txt', $content);

            $filename1 = $generator->resolve($file1);
            $filename2 = $generator->resolve($file2);

            expect($filename1)->toBe($filename2);
        });

        it('generates different filename for different content', function (): void {
            $generator = new FilenameGenerator('sha256');

            $file1 = UploadedFile::fake()->createWithContent('test1.txt', 'content 1');
            $file2 = UploadedFile::fake()->createWithContent('test2.txt', 'content 2');

            $filename1 = $generator->resolve($file1);
            $filename2 = $generator->resolve($file2);

            expect($filename1)->not()->toBe($filename2);
        });

        it('enables content-based deduplication', function (): void {
            $generator = new FilenameGenerator('sha256');

            // Two files with same content
            $content = 'duplicate content';
            $file1 = UploadedFile::fake()->createWithContent('file1.txt', $content);
            $file2 = UploadedFile::fake()->createWithContent('file2.txt', $content);

            $hash1 = $generator->resolve($file1);
            $hash2 = $generator->resolve($file2);

            // Both should generate same filename (content-based)
            expect($hash1)->toBe($hash2);
            // Basename should be identical (for storage deduplication)
            expect(basename($hash1))->toBe(basename($hash2));
        });
    });

    describe('error handling', function (): void {
        it('throws exception for invalid strategy', function (): void {
            $generator = new FilenameGenerator('invalid');
            $file = UploadedFile::fake()->create('test.txt', 100);

            $generator->resolve($file);
        })->throws(\InvalidArgumentException::class);
    });
});
