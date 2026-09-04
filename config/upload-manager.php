<?php

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
