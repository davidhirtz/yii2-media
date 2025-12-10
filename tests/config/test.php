<?php

declare(strict_types=1);

use Hirtz\Media\Bootstrap;

return [
    'bootstrap' => [
        Bootstrap::class,
    ],
    'components' => [
        'db' => [
            'dsn' => getenv('MYSQL_DSN') ?: 'mysql:host=127.0.0.1;dbname=yii2_test',
            'username' => getenv('MYSQL_USER') ?: 'root',
            'password' => getenv('MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8',
        ],
    ],
    'modules' => [
        'media' => [
            'transformations' => [
                'xs' => [
                    'width' => 100,
                ],
                'sm' => [
                    'width' => 200,
                ],
                'md' => [
                    'width' => 300,
                ],
            ],
        ],
    ],
    'params' => [
        'cookieValidationKey' => 'test',
    ],
];
