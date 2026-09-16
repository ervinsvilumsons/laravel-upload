<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelUpload\Tests;

use ErvinsVilumsons\LaravelUpload\Facades\UploadManager;
use ErvinsVilumsons\LaravelUpload\UploadManagerServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            UploadManagerServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'UploadManager' => UploadManager::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $testStoragePath = $this->getTestStoragePath($app);

        // Set up test filesystem disk.
        $config->set('filesystems.disks.test', [
            'driver' => 'local',
            'root' => $testStoragePath,
            'url' => '/storage',
            'visibility' => 'public',
        ]);

        // Set test as default disk.
        $config->set('filesystems.default', 'test');

        // Configure upload manager for tests.
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

        $testStoragePath = $this->getTestStoragePath($this->application());

        if (! is_dir($testStoragePath)) {
            mkdir($testStoragePath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $testStoragePath = $this->getTestStoragePath($this->application());

        $this->deleteDirectory($testStoragePath);

        parent::tearDown();
    }

    protected function application(): Application
    {
        /** @var Application $app */
        $app = $this->app;

        return $app;
    }

    protected function getTestStoragePath(Application $app): string
    {
        $token = getenv('TEST_TOKEN') ?: '0';

        return $app->basePath(
            'storage'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.$token
        );
    }

    protected function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path), ['.', '..']) as $file) {
            $filePath = $path.DIRECTORY_SEPARATOR.$file;

            if (is_dir($filePath) && ! is_link($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        rmdir($path);
    }
}
