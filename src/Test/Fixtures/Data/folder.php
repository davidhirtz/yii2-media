<?php

declare(strict_types=1);

use Hirtz\Media\Models\Folder;
use yii\db\Expression;

return [
    'folder-1' => [
        'id' => 1,
        'type' => Folder::TYPE_DEFAULT,
        'name' => 'Default',
        'position' => 1,
        'path' => 'default',
        'file_count' => 6,
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
];
