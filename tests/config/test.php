<?php

use yii\web\Session;

return [
    'id' => 'yii2-media',
    'aliases' => [
        // This is a fix for the broken aliasing of `BaseMigrateController::getNamespacePath()`
        '@davidhirtz/yii2/media' => __DIR__ . '/../../src/',
    ],
    'components' => [
        'assetManager' => [
            'linkAssets' => true,
        ],
        'db' => [
            'dsn' => getenv('MYSQL_DSN') ?: 'mysql:host=127.0.0.1;dbname=yii2_media_test',
            'username' => getenv('MYSQL_USER') ?: 'root',
            'password' => getenv('MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8',
            ...is_file(__DIR__ . '/db.php') ? require (__DIR__ . '/db.php') : [],
        ],
        'mailer' => [
            'useFileTransport' => true,
        ],
        'session' => [
            'class' => Session::class,
        ],
        'urlManager' => [
            'showScriptName' => false,
        ],
    ],
    'params' => [
        'cookieValidationKey' => 'test',
    ],
];
