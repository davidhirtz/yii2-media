<?php

declare(strict_types=1);

/**
 * `keepMessages` names the keys no call site can reach: the permission's description lives in the
 * `auth_item.description` a migration seeds, as a `Message` pointer inside an SQL string the tokenizer never
 * reads, so `removeUnused` deleted it on every run (monorepo issue #211). Add one here beside the migration.
 */
return [
    ...require Yii::getAlias('@skeleton/../messages/config.php'),
    'messagePath' => __DIR__,
    'categories' => ['media'],
    'keepMessages' => [
        'media' => [
            'AUTH_FILE_DESCRIPTION',
            'AUTH_FOLDER_DESCRIPTION',
        ],
    ],
];
