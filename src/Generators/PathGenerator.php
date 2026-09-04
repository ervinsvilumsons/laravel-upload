<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Generators;

use ErvinsVilumsons\LaravelUpload\Contracts\PathGeneratorContract;

final class PathGenerator implements PathGeneratorContract
{
    protected string $directory;

    public function __construct(string $directory = '/')
    {
        $this->directory = trim($directory, '/');
    }

    public function resolve(string $name): string
    {
        $name = trim($name, '/');

        $directory = $this->resolveDatePlaceholders($this->directory);

        return $directory === '' ? $name : "{$directory}/{$name}";

    }

    private function resolveDatePlaceholders(string $directory): string
    {
        $date = now();

        return str_replace(
            [
                '{year}',
                '{month}',
                '{day}',
            ],
            [
                $date->format('Y'),
                $date->format('m'),
                $date->format('d'),
            ],
            $directory,
        );
    }
}
