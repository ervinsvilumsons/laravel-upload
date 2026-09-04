<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Upload;

final readonly class UploadResult
{
    public string $name;

    public string $originalName;

    public string $extension;

    public ?string $mimeType;

    public ?int $size;

    public string $path;

    public string $url;

    public ?string $contentHash;

    /**
     * @param  array{name: string, originalName: string, extension: string, mimeType?: ?string, size?: ?int, path: string, url: string, contentHash?: ?string}  $data
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->originalName = $data['originalName'];
        $this->extension = $data['extension'];
        $this->mimeType = $data['mimeType'] ?? null;
        $this->size = $data['size'] ?? null;
        $this->path = $data['path'];
        $this->url = $data['url'];
        $this->contentHash = $data['contentHash'] ?? null;
    }
}
