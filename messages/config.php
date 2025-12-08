<?php

declare(strict_types=1);

$config = require Yii::getAlias('@skeleton/../messages/config.php');

return [
    ...$config,
    'sourcePath' => __DIR__ . '/../src/',
    'messagePath' => __DIR__,
    'ignoreCategories' => [
        'skeleton',
        'yii',
    ],
];
