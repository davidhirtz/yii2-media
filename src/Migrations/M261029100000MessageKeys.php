<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use Override;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M261029100000MessageKeys extends Migration
{
    use MigrationTrait;

    private const array KEYS = [
        'FILE_REFERENCE_CLEARED' => 'FILE_REFERENCE_CLEAR_TRAIL',
        'REORDER_ASSETS_ASSET_ORDER_CHANGED' => 'ASSET_ORDER_TRAIL',
        'REORDER_FOLDER_FOLDER_ORDER_CHANGED' => 'FOLDER_ORDER_TRAIL',
    ];

    #[Override]
    public function safeUp(): void
    {
        $this->renameMessageKeys('media', self::KEYS);
    }

    #[Override]
    public function safeDown(): void
    {
        $this->renameMessageKeys('media', array_flip(self::KEYS));
    }
}
