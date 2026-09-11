<?php

declare(strict_types=1);

use Hirtz\Media\Models\File;
use yii\db\Expression;

return [
    'file-1' => [
        'id' => 1,
        'status' => File::STATUS_ENABLED,
        'folder_id' => 1,
        'name' => 'Test 1',
        'basename' => 'test-1',
        'extension' => 'jpg',
        'width' => 1000,
        'height' => 1000,
        'alt_text' => 'Alt Text 1',
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
    'file-2' => [
        'id' => 2,
        'status' => File::STATUS_ENABLED,
        'folder_id' => 1,
        'name' => 'Test 2',
        'basename' => 'test-2',
        'extension' => 'svg',
        'width' => 1000,
        'height' => 1000,
        'alt_text' => 'Alt Text 2',
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
    'file-3' => [
        'id' => 3,
        'status' => File::STATUS_ENABLED,
        'folder_id' => 1,
        'name' => 'Test 3',
        'basename' => 'test-3',
        'extension' => 'jpg',
        'width' => 20,
        'height' => 20,
        'alt_text' => 'Alt Text 3',
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
    'file-4' => [
        'id' => 4,
        'status' => File::STATUS_ENABLED,
        'folder_id' => 1,
        'name' => 'Test 4',
        'basename' => 'test-4',
        'extension' => 'jpg',
        'width' => 20,
        'height' => 20,
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
    'file-5' => [
        'id' => 5,
        'status' => File::STATUS_ENABLED,
        'folder_id' => 1,
        'name' => 'Test 5',
        'basename' => 'test-5',
        'extension' => 'jpg',
        'width' => 20,
        'height' => 20,
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
    'file-6' => [
        'id' => 6,
        'status' => File::STATUS_DRAFT,
        'folder_id' => 1,
        'name' => 'Test 6',
        'basename' => 'test-6',
        'extension' => 'jpg',
        'width' => 20,
        'height' => 20,
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
];
