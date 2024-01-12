<?php

namespace davidhirtz\yii2\media\migrations;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\skeleton\db\traits\MigrationTrait;
use Yii;
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

        $this->createIndexAfterDeletingDuplicates('basename', File::tableName(), ['basename', 'folder_id', 'extension']);
        $this->createIndexAfterDeletingDuplicates('name', Transformation::tableName(), ['name', 'file_id', 'extension']);

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

    protected function createIndexAfterDeletingDuplicates(string $name, string $tableName, array $columns, bool $unique = true): void
    {
        $quotedColumns = array_map(fn ($column) => "[[$column]]", $columns);
        $quotedColumns = implode(', ', $quotedColumns);

        $sql = "DELETE FROM $tableName WHERE `id` NOT IN (SELECT MIN(`id`) FROM (SELECT * FROM $tableName) AS `tmp` GROUP BY $quotedColumns)";
        Yii::$app->getDb()->createCommand($sql)->execute();

        $this->createIndex($name, $tableName, $columns, $unique);
    }
}
