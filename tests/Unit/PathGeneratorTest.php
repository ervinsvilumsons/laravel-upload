<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests\Unit;

use ErvinsVilumsons\LaravelUpload\Generators\PathGenerator;
use Illuminate\Support\Carbon;

describe('PathGenerator', function (): void {
    describe('basic path generation', function (): void {
        it('constructs path with directory and filename', function (): void {
            $generator = new PathGenerator('uploads');

            $path = $generator->resolve('document.pdf');

            expect($path)->toBe('uploads/document.pdf');
        });

        it('handles root directory', function (): void {
            $generator = new PathGenerator('/');

            $path = $generator->resolve('file.txt');

            expect($path)->toBe('file.txt');
        });

        it('strips leading and trailing slashes', function (): void {
            $generator = new PathGenerator('/uploads/');

            $path = $generator->resolve('/document.pdf/');

            expect($path)->toBe('uploads/document.pdf');
        });

        it('handles nested directories', function (): void {
            $generator = new PathGenerator('uploads/documents/2026');

            $path = $generator->resolve('file.txt');

            expect($path)->toBe('uploads/documents/2026/file.txt');
        });
    });

    describe('date placeholder resolution', function (): void {
        beforeEach(function (): void {
            // Mock current date to 2026-09-15
            Carbon::setTestNow('2026-09-15 12:00:00');
        });

        afterEach(function (): void {
            Carbon::setTestNow();
        });

        it('resolves {year} placeholder', function (): void {
            $generator = new PathGenerator('uploads/{year}');

            $path = $generator->resolve('file.txt');

            expect($path)->toBe('uploads/2026/file.txt');
        });

        it('resolves {month} placeholder', function (): void {
            $generator = new PathGenerator('uploads/{month}');

            $path = $generator->resolve('file.txt');

            expect($path)->toBe('uploads/09/file.txt');
        });

        it('resolves {day} placeholder', function (): void {
            $generator = new PathGenerator('uploads/{day}');

            $path = $generator->resolve('file.txt');

            expect($path)->toBe('uploads/15/file.txt');
        });

        it('resolves all date placeholders together', function (): void {
            $generator = new PathGenerator('uploads/{year}/{month}/{day}');

            $path = $generator->resolve('file.txt');

            expect($path)->toBe('uploads/2026/09/15/file.txt');
        });

        it('resolves mixed date and static paths', function (): void {
            $generator = new PathGenerator('storage/documents/{year}/{month}');

            $path = $generator->resolve('report.pdf');

            expect($path)->toBe('storage/documents/2026/09/report.pdf');
        });

        it('pads month and day with zeros', function (): void {
            Carbon::setTestNow('2026-01-05 12:00:00');
            $generator = new PathGenerator('uploads/{year}-{month}-{day}');

            $path = $generator->resolve('file.txt');

            expect($path)->toBe('uploads/2026-01-05/file.txt');
        });
    });

    describe('backwards compatibility', function (): void {
        it('resolve method works', function (): void {
            $generator = new PathGenerator('uploads');

            $path = $generator->resolve('document.pdf');

            expect($path)->toBe('uploads/document.pdf');
        });
    });

    describe('empty paths', function (): void {
        it('returns only filename when directory is empty', function (): void {
            $generator = new PathGenerator('');

            $path = $generator->resolve('file.txt');

            expect($path)->toBe('file.txt');
        });

        it('returns only filename for root directory', function (): void {
            $generator = new PathGenerator('/');

            $path = $generator->resolve('file.txt');

            expect($path)->toBe('file.txt');
        });
    });
});
