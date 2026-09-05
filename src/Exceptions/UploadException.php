<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Exceptions;

use Throwable;

class UploadException extends \RuntimeException
{
    public const FILE_NOT_FOUND = 1001;

    public const INVALID_FILE = 1002;

    public const UPLOAD_FAILED = 1003;

    public const INVALID_FILESYSTEM = 1004;

    public const INVALID_PROFILE = 1005;

    public const FILE_TOO_LARGE = 1006;

    public const STREAM_FAILED = 1007;

    public const ENCRYPTION_FAILED = 1008;

    /**
     * @var array<int, string>
     */
    protected static array $messages = [
        self::FILE_NOT_FOUND => 'The file does not exist.',
        self::INVALID_FILE => 'The provided file is invalid.',
        self::UPLOAD_FAILED => 'The file could not be uploaded.',
        self::INVALID_FILESYSTEM => 'The configured filesystem does not support URLs.',
        self::FILE_TOO_LARGE => 'The uploaded file is too large.',
        self::STREAM_FAILED => 'Unable to stage file for upload.',
        self::ENCRYPTION_FAILED => 'Encryption failed.',
    ];

    public function __construct(int $code, ?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct(
            $message ?? self::$messages[$code] ?? 'An upload error occurred.',
            $code,
            $previous,
        );
    }

    public static function fileNotFound(?string $message = null): self
    {
        return new self(self::FILE_NOT_FOUND, $message);
    }

    public static function invalidFile(?string $message = null): self
    {
        return new self(self::INVALID_FILE, $message);
    }

    public static function uploadFailed(?string $message = null, ?Throwable $previous = null): self
    {
        return new self(self::UPLOAD_FAILED, $message, $previous);
    }

    public static function invalidFilesystem(?string $message = null): self
    {
        return new self(self::INVALID_FILESYSTEM, $message);
    }

    public static function invalidPRofile(string $profileName): self
    {
        return new self(self::INVALID_PROFILE, "Profile '{$profileName}' not found.");
    }

    public static function fileTooLarge(?string $message = null): self
    {
        return new self(self::FILE_TOO_LARGE, $message);
    }

    public static function streamFailed(?string $message = null): self
    {
        return new self(self::STREAM_FAILED, $message);
    }

    public static function encryptionFailed(?string $message = null): self
    {
        return new self(self::ENCRYPTION_FAILED, $message);
    }
}
