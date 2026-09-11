<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M260911140000CustomAttributes extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $this->addCustomAttributesColumn(File::tableName());
    }

    public function safeDown(): void
    {
        $this->dropCustomAttributesColumn(File::tableName());
    }
}
