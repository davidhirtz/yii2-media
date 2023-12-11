<?php

namespace davidhirtz\yii2\media\migrations;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\db\traits\MigrationTrait;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */

class M231211093758Indexes extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $this->dropColumn(Folder::tableName(), 'parent_id');
        $this->dropColumn(Folder::tableName(), 'lft');
        $this->dropColumn(Folder::tableName(), 'rgt');

        $this->createIndex('path', Folder::tableName(), 'path', true);

        parent::safeUp();
    }

    public function safeDown(): void
    {
        $this->dropIndex('path', Folder::tableName());

        $this->addColumn(Folder::tableName(), 'parent_id', $this->integer()->null()->after('type'));
        $this->addColumn(Folder::tableName(), 'lft', $this->integer()->null()->after('parent_id'));
        $this->addColumn(Folder::tableName(), 'rgt', $this->integer()->null()->after('lft'));

        parent::safeDown();
    }
}