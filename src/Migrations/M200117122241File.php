<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M200117122241File extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $this->addColumn(File::tableName(), 'alt_text', (string)$this->string(250)
            ->null()
            ->after('size'));
    }

    public function safeDown(): void
    {
        $this->dropColumn(File::tableName(), 'alt_text');
    }
}
