<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests;

use ErvinsVilumsons\LaravelUpload\Facades\UploadManager;
use ErvinsVilumsons\LaravelUpload\UploadManagerServiceProvider;
use Illuminate\Config\Repository;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            UploadManagerServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'UploadManager' => UploadManager::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        /** @var Repository $config */
        $config = $app['config'];

        // Set up test filesystem disk
        $config->set('filesystems.disks.test', [
            'driver' => 'local',
            'root' => storage_path('testing'),
            'url' => '/storage',
            'visibility' => 'public',
        ]);

        // Set test as default disk
        $config->set('filesystems.default', 'test');

        // Configure upload manager for tests
        $config->set('upload-manager', [
            'default' => [
                'disk' => 'test',
                'path' => 'uploads',
                'filename' => 'uuid',
                'hash' => false,
                'encrypt' => false,
            ],
            'profiles' => [
                'documents' => [
                    'disk' => 'test',
                    'path' => 'documents',
                    'filename' => 'sha256',
                    'hash' => true,
                ],
                'images' => [
                    'disk' => 'test',
                    'path' => 'images/{year}/{month}/{day}',
                    'filename' => 'uuid',
                    'hash' => true,
                ],
                'encrypted' => [
                    'disk' => 'test',
                    'path' => 'encrypted',
                    'filename' => 'uuid',
                    'hash' => false,
                    'encrypt' => true,
                ],
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create test storage directory
        $testStoragePath = storage_path('testing');
        if (! is_dir($testStoragePath)) {
            mkdir($testStoragePath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $testStoragePath = storage_path('testing');
        if (is_dir($testStoragePath)) {
            $this->deleteDirectory($testStoragePath);
        }

        parent::tearDown();
    }

    protected function deleteDirectory(string $path): void
    {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $path.DIRECTORY_SEPARATOR.$file;
                if (is_dir($filePath)) {
                    $this->deleteDirectory($filePath);
                } else {
                    unlink($filePath);
                }
            }
            rmdir($path);
        }
    }
}
