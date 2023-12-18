<?php

namespace davidhirtz\yii2\media\migrations;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\Transformation;
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
        $this->dropForeignKey('folder_parent_id_ibfk', Folder::tableName());

        $this->dropColumn(Folder::tableName(), 'parent_id');
        $this->dropColumn(Folder::tableName(), 'lft');
        $this->dropColumn(Folder::tableName(), 'rgt');

        $this->createIndex('path', Folder::tableName(), 'path', true);
        $this->createIndex('basename', File::tableName(), ['basename', 'folder_id', 'extension'], true);
        $this->createIndex('name', Transformation::tableName(), ['name', 'file_id', 'extension'], true);

        parent::safeUp();
    }

    public function safeDown(): void
    {
        $this->dropIndex('path', Folder::tableName());
        $this->dropIndex('basename', File::tableName());
        $this->dropIndex('name', Transformation::tableName());

        $this->addColumn(Folder::tableName(), 'parent_id', $this->integer()->unsigned()->null()->after('type'));
        $this->addColumn(Folder::tableName(), 'lft', $this->integer()->unsigned()->null()->after('parent_id'));
        $this->addColumn(Folder::tableName(), 'rgt', $this->integer()->unsigned()->null()->after('lft'));

        $this->addForeignKey(
            'folder_parent_id_ibfk',
            Folder::tableName(),
            'parent_id',
            Folder::tableName(),
            'id',
            'SET NULL'
        );

        parent::safeDown();
    }
}
