<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Contracts;

interface PathGeneratorContract
{
    public function __construct(string $directory = '/');

    public function resolve(string $name): string;
}
