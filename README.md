# Laravel Upload Manager

![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)
![Laravel 10+](https://img.shields.io/badge/Laravel-10%2B-FF2D20?logo=laravel&logoColor=white)
![Tests](https://img.shields.io/badge/tests-passing-brightgreen)
![Coverage](https://img.shields.io/badge/coverage-88%25-brightgreen)
![License](https://img.shields.io/badge/license-MIT-green)

A stream-aware file upload package for Laravel with hashing, encryption, deduplication, and configurable upload profiles.

## 🧩 Features

- Stream large files without loading them entirely into memory
- SHA-256 and other content hashing
- Optional streaming encryption
- Content-based filenames and deduplication
- Configurable upload profiles
- Dynamic paths such as `uploads/{year}/{month}/{day}`
- Extensible architecture

## 📦 Installation

```bash
composer require ervinsvilumsons/laravel-upload
```

Publish the configuration:

```bash
php artisan vendor:publish --provider="ErvinsVilumsons\LaravelUpload\UploadManagerServiceProvider"
```

## 🚀 Quick Start

```php
use UploadManager;

$result = UploadManager::profile('default')->upload($request->file('document'));
```

### Profiles

Configure different upload strategies in `config/upload-manager.php`:

```php
return [
    'default' => [
        'disk' => 'local',
        'path' => 'uploads/{year}/{month}/{day}',
        'filename' => 'uuid',
        'hash' => false,
        'encrypt' => false,
    ],

    'profiles' => [
        'documents' => [
            'path' => 'documents',
            'filename' => 'sha256',
            'hash' => true,
        ],

        'secure' => [
            'disk' => 's3',
            'path' => 'secure',
            'filename' => 'sha256',
            'encrypt' => true,
        ],
    ],
];
```

### Upload Result

```php
$result->path;
$result->url;
$result->size;
$result->contentHash;
$result->name;
$result->originalName;
$result->extension;
$result->mimeType;
```

## ⚖️ License

[MIT](LICENSE)