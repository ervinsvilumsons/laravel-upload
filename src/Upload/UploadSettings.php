<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Upload;

use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;

final class UploadSettings
{
    /**
     * @return array<string, mixed> $settings
     */
    public static function normalize(mixed $settings): array
    {
        if (! is_array($settings)) {
            return [];
        }

        return array_filter($settings, static fn (mixed $value, mixed $key): bool => is_string($key), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array<string, mixed> $profileName
     */
    public static function profile(string $profileName): array
    {
        $profiles = config('upload-manager.profiles', []);

        if (! is_array($profiles) || ! array_key_exists($profileName, $profiles)) {
            throw UploadException::invalidProfile($profileName);
        }

        return self::normalize($profiles[$profileName]);
    }
}
