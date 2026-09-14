<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M260915140000CustomAttributesColumn extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $this->moveCustomAttributesColumn(File::tableName(), 'alt_text');
        $this->moveCustomAttributesColumn(Asset::tableName(), 'file_id');
    }

    public function safeDown(): void
    {
        $this->moveCustomAttributesColumnToEnd(Asset::tableName());
        $this->moveCustomAttributesColumnToEnd(File::tableName());
    }
}
