<?php

declare(strict_types=1);

namespace Hirtz\Media\migrations;

use Hirtz\Media\models\File;
use Hirtz\Skeleton\db\traits\MigrationTrait;
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
        $this->alterColumn(File::tableName(), 'folder_id', (string)$this->integer()->unsigned()->notNull());
        parent::safeUp();
    }
}
