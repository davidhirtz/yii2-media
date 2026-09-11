<?php

declare(strict_types=1);

use Hirtz\Media\Bootstrap;
use Hirtz\Media\Test\Models\TestAsset;

$basePath = (getenv('BASE_PATH') ?: getcwd());
$config = require("$basePath/vendor/davidhirtz/yii2-skeleton/config/test.php");

return [
    ...$config,
    'bootstrap' => [
        Bootstrap::class,
    ],
    'modules' => [
        'media' => [
            'assets' => [
                TestAsset::class,
            ],
        ],
    ],
];
