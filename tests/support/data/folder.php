<?php

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\models\User;
use yii\db\Expression;

return [
    'default' => [
        'id' => 1,
        'type' => Folder::TYPE_DEFAULT,
        'name' => 'default',
        'path' => 'default',
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
];
