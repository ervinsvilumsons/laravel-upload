<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Unit;

use ErvinsVilumsons\LaravelUpload\Hash\StreamHasher;
use Illuminate\Http\UploadedFile;

describe('StreamHasher', function (): void {
    $hasher = new StreamHasher;

    describe('hash()', function () use ($hasher): void {
        it('computes sha256 hash of file content', function () use ($hasher): void {
            $content = 'test file content';
            $file = UploadedFile::fake()->createWithContent('test.txt', $content);

            $hash = $hasher->hash($file, 'sha256');

            expect($hash)->toBe(hash('sha256', $content));
        });

        it('computes md5 hash of file', function () use ($hasher): void {
            $content = 'test content for md5';
            $file = UploadedFile::fake()->createWithContent('test.txt', $content);

            $hash = $hasher->hash($file, 'md5');

            expect($hash)->toBe(hash('md5', $content));
        });

        it('computes sha1 hash of file', function () use ($hasher): void {
            $content = 'test content for sha1';
            $file = UploadedFile::fake()->createWithContent('test.txt', $content);

            $hash = $hasher->hash($file, 'sha1');

            expect($hash)->toBe(hash('sha1', $content));
        });

        it('handles large files without buffering', function () use ($hasher): void {
            // Create a 10MB fake file
            $largeFile = UploadedFile::fake()->create('large.bin', 10000);

            $hash = $hasher->hash($largeFile, 'sha256');

            expect($hash)->toBeString()->not()->toBeEmpty();
            expect(strlen($hash))->toBe(64); // SHA256 hex length
        });

        it('produces same hash for identical content', function () use ($hasher): void {
            $content = 'identical content';
            $file1 = UploadedFile::fake()->createWithContent('test1.txt', $content);
            $file2 = UploadedFile::fake()->createWithContent('test2.txt', $content);

            $hash1 = $hasher->hash($file1, 'sha256');
            $hash2 = $hasher->hash($file2, 'sha256');

            expect($hash1)->toBe($hash2);
        });

        it('produces different hash for different content', function () use ($hasher): void {
            $file1 = UploadedFile::fake()->createWithContent('test1.txt', 'content 1');
            $file2 = UploadedFile::fake()->createWithContent('test2.txt', 'content 2');

            $hash1 = $hasher->hash($file1, 'sha256');
            $hash2 = $hasher->hash($file2, 'sha256');

            expect($hash1)->not()->toBe($hash2);
        });

        it('handles file paths as well as UploadedFile objects', function () use ($hasher): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test_hash_');
            file_put_contents($tempFile, 'path based content');

            try {
                $hash = $hasher->hash($tempFile, 'sha256');

                expect($hash)->toBe(hash('sha256', 'path based content'));
            } finally {
                unlink($tempFile);
            }
        });

        it('throws exception for non-existent file', function () use ($hasher): void {
            $hasher->hash('/non/existent/file.txt', 'sha256');
        })->throws(\RuntimeException::class);
    });

    describe('chunk processing', function () use ($hasher): void {
        it('correctly hashes content regardless of chunk size', function () use ($hasher): void {
            $content = 'a'.str_repeat('b', 20000).'c'; // ~20KB
            $file = UploadedFile::fake()->createWithContent('test.txt', $content);

            $hash1KB = $hasher->hash($file, 'sha256', 1024);
            $hash8KB = $hasher->hash($file, 'sha256', 8192);
            $hash16KB = $hasher->hash($file, 'sha256', 16384);

            $expectedHash = hash('sha256', $content);

            expect($hash1KB)->toBe($expectedHash);
            expect($hash8KB)->toBe($expectedHash);
            expect($hash16KB)->toBe($expectedHash);
        });

        it('handles various chunk sizes', function () use ($hasher): void {
            $file = UploadedFile::fake()->create('test.txt', 5000);

            foreach ([512, 1024, 4096, 8192, 16384] as $chunkSize) {
                $hash = $hasher->hash($file, 'sha256', $chunkSize);
                expect($hash)->toBeString()->not()->toBeEmpty();
            }
        });
    });
});
