# Laravel Upload Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ervinsvilumsons/laravel-upload.svg?style=flat-square)](https://packagist.org/packages/ervinsvilumsons/laravel-upload)
![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php)
![Laravel 11+](https://img.shields.io/badge/Laravel-11%2B-FF2D20?logo=laravel&logoColor=white)
[![License](https://img.shields.io/github/license/ervinsvilumsons/laravel-upload)](https://github.com/ervinsvilumsons/laravel-upload/blob/main/LICENSE)

[![Tests](https://github.com/ervinsvilumsons/laravel-upload/actions/workflows/ci.yml/badge.svg)](https://github.com/ervinsvilumsons/laravel-upload/actions/workflows/ci.yml)
[![codecov](https://codecov.io/github/ervinsvilumsons/laravel-upload/branch/staging/graph/badge.svg?token=QJZMUSPBAL)](https://codecov.io/github/ervinsvilumsons/laravel-upload)
[![Quality](https://sonarcloud.io/api/project_badges/measure?project=ervinsvilumsons_laravel-upload&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=ervinsvilumsons_laravel-upload)
[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2Fervinsvilumsons%2Flaravel-upload.svg?type=shield&issueType=license)](https://app.fossa.com/projects/git%2Bgithub.com%2Fervinsvilumsons%2Flaravel-upload?ref=badge_shield&issueType=license)
[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2Fervinsvilumsons%2Flaravel-upload.svg?type=shield&issueType=security)](https://app.fossa.com/projects/git%2Bgithub.com%2Fervinsvilumsons%2Flaravel-upload?ref=badge_shield&issueType=security)

A stream-aware file upload package for Laravel with hashing, encryption, deduplication, and configurable upload profiles.

## 🧩 Features

- Stream large files without loading them entirely into memory
- SHA-256 content hashing
- Optional streaming encryption
- Content-based filenames and deduplication
- Configurable upload profiles
- Dynamic paths such as `uploads/{year}/{month}/{day}`

## 📦 Installation

```bash
composer require ervinsvilumsons/laravel-upload
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=upload-manager
```

## 🚀 Quick Start

```php
use UploadManager;

$result = UploadManager::profile('documents')->upload($request->file('document'));
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

Laravel Upload Manager is released under the [MIT License](LICENSE).
