<?php

declare(strict_types=1);

use Hirtz\Media\Bootstrap;

$basePath = (getenv('BASE_PATH') ?: getcwd());
$config = require("$basePath/vendor/davidhirtz/yii2-skeleton/config/test.php");

return [
    ...$config,
    'bootstrap' => [
        Bootstrap::class,
    ],
];
