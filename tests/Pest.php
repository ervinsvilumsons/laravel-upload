<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests;

use Illuminate\Http\UploadedFile;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that will be the PHPUnit\Framework\TestCase class. Of course, you
| may need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Unit', 'Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can call
| on your value. By default, we have a handful of helpful expectations defined for you. However,
| you're free to add your own using the "extend()" function. Build your own language!
|
*/

expect()->extend('toBeWithinRange', fn (int $min, int $max) => $this
    ->toBeGreaterThanOrEqual($min)
    ->toBeLessThanOrEqual($max));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
| While Pest is very powerful out-of-the-box with PHPUnit assertions and
| expectation helpers, you may have some testing code specific to your
| project that you'd like to abstract into helper functions. You're free
| to add them here. We've already started you off with a handful of the
| most useful ones related to Laravel testing!
|
*/

function mockUploadedFile(string $name = 'test.txt', int $size = 1000, ?string $content = null): UploadedFile
{
    if ($content !== null) {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    return UploadedFile::fake()->create($name, $size);
}
