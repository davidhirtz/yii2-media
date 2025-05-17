<?php

namespace davidhirtz\yii2\media\migrations;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\db\traits\MigrationTrait;
use yii\db\Migration;

/**
 * Removes the default value of `folder_id` in the file table. This was fixed in {@link M190320181218Media} in 2.2.5.
 * @noinspection PhpUnused
 */
class M250505095258FileFolderId extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $this->alterColumn(File::tableName(), 'folder_id', $this->integer()->unsigned()->notNull());
        parent::safeUp();
    }
}
