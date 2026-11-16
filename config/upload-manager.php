<?php

declare(strict_types=1);

return [

    'default' => [
        'disk' => env('FILESYSTEM_DISK', 'local'),
        'path' => 'uploads',
        'filename' => 'uuid',
        'hash' => false,
        'encrypt' => false,
    ],

    'profiles' => [],
];
