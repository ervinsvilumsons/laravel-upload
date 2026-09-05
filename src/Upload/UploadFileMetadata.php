<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Upload;

use ErvinsVilumsons\LaravelUpload\Exceptions\UploadException;
use Illuminate\Http\UploadedFile;

final class UploadFileMetadata
{
    public function originalName(UploadedFile|string $file): string
    {
        return $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);
    }

    public function extension(UploadedFile|string $file): string
    {
        return $file instanceof UploadedFile ? $file->getClientOriginalExtension() : pathinfo($file, PATHINFO_EXTENSION);
    }

    public function mimeType(UploadedFile|string $file): ?string
    {
        $mime = $file instanceof UploadedFile ? $file->getMimeType() : mime_content_type($file);

        return $mime ?: null;
    }

    public function size(UploadedFile|string $file): ?int
    {
        $size = $file instanceof UploadedFile
            ? $file->getSize()
            : (is_file($file) ? filesize($file) : null);

        return $size === false ? null : $size;
    }

    public function validateSize(UploadedFile|string $file, ?int $fileSize): void
    {
        if ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_INI_SIZE) {
            throw UploadException::fileTooLarge();
        }

        if ($fileSize === null) {
            return;
        }

        $limits = array_filter([$this->iniSizeInBytes('upload_max_filesize'), $this->iniSizeInBytes('post_max_size')], static fn (?int $limit): bool => $limit !== null);

        if ($limits !== [] && $fileSize > min($limits)) {
            throw UploadException::fileTooLarge();
        }
    }

    private function iniSizeInBytes(string $setting): ?int
    {
        $value = trim((string) ini_get($setting));

        if ($value === '' || $value === '-1' || ! preg_match('/^(\d+(?:\.\d+)?)\s*([kmgtpe]?b?)?$/i', $value, $matches)) {
            return null;
        }

        $multipliers = [
            '' => 1,
            'b' => 1,
            'k' => 1024,
            'kb' => 1024,
            'm' => 1024 ** 2,
            'mb' => 1024 ** 2,
            'g' => 1024 ** 3,
            'gb' => 1024 ** 3,
            't' => 1024 ** 4,
            'tb' => 1024 ** 4,
            'p' => 1024 ** 5,
            'pb' => 1024 ** 5,
            'e' => 1024 ** 6,
            'eb' => 1024 ** 6,
        ];

        $unit = strtolower($matches[2] ?? '');

        return isset($multipliers[$unit]) ? (int) ((float) $matches[1] * $multipliers[$unit]) : null;
    }
}
