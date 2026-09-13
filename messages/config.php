<?php

declare(strict_types=1);

$config = require Yii::getAlias('@skeleton/../messages/config.php');

return [
    ...$config,
    'sourcePath' => __DIR__ . '/../src/',
    'messagePath' => __DIR__,
    // `Message::make()` stores a pointer instead of rendered text, so its keys live nowhere else
    'translator' => ['Yii::t', '\\Yii::t', 'Message::make'],
    'ignoreCategories' => [
        'skeleton',
        'yii',
    ],
];
