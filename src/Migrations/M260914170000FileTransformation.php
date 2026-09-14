<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\FileTransformation;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M260914170000FileTransformation extends Migration
{
    use MigrationTrait;

    private const string LEGACY_TRANSFORMATION_TABLE = '{{%transformation}}';

    public function safeUp(): void
    {
        $this->renameTable(self::LEGACY_TRANSFORMATION_TABLE, FileTransformation::tableName());
    }

    public function safeDown(): void
    {
        $this->renameTable(FileTransformation::tableName(), self::LEGACY_TRANSFORMATION_TABLE);
    }
}
