<?php
/**
 * This is the configuration for generating message translations
 * for the Yii framework. It is used by the 'yii message' command.
 */
return [
    'sourcePath' => dirname(__DIR__),
    'messagePath' => __DIR__,
    'languages' => ['de', 'en-US'],
    'ignoreCategories' => ['yii', 'skeleton'],
    'overwrite' => true,
    'removeUnused' => true,
    'only' => ['*.php'],
    'format' => 'php',
    'sort' => true,
    'except' => [
        '/config',
        '/messages',
        '/tests',
    ],
];
