<?php

declare(strict_types=1);

namespace Hirtz\Media\migrations;

use Hirtz\Media\models\File;
use Hirtz\Skeleton\db\traits\MigrationTrait;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M200117122241File extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $file = File::instance();
        $after = 'size';

        foreach ($file->getI18nAttributeNames('alt_text') as $attributeName) {
            $this->addColumn(File::tableName(), $attributeName, (string)$this->string(250)
                ->null()
                ->after($after));

            $after = $attributeName;
        }
    }

    public function safeDown(): void
    {
        $file = File::instance();

        foreach ($file->getI18nAttributeNames('alt_text') as $attributeName) {
            $this->dropColumn(File::tableName(), $attributeName);
        }
    }
}
