<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\migrations;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\db\traits\MigrationTrait;
use davidhirtz\yii2\skeleton\models\User;
use Yii;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M190320181218Media extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $schema = $this->getDb()->getSchema();

        $this->createTable(Folder::tableName(), [
            'id' => $this->primaryKey()->unsigned(),
            'type' => $this->smallInteger()->notNull()->defaultValue(Folder::TYPE_DEFAULT),
            'parent_id' => $this->integer()->unsigned()->null(),
            'lft' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'rgt' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'position' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'name' => $this->string(Folder::PATH_MAX_LENGTH)->notNull(),
            'path' => $this->string(Folder::PATH_MAX_LENGTH)->notNull(),
            'file_count' => $this->integer()->notNull()->defaultValue(0),
            'updated_by_user_id' => $this->integer()->unsigned()->null(),
            'updated_at' => $this->dateTime(),
            'created_at' => $this->dateTime()->notNull(),
        ], $this->getTableOptions());

        $tableName = $schema->getRawTableName(Folder::tableName());
        $this->addForeignKey($tableName . '_parent_id_ibfk', Folder::tableName(), 'parent_id', Folder::tableName(), 'id', 'SET NULL');
        $this->addForeignKey($tableName . '_updated_by_ibfk', Folder::tableName(), 'updated_by_user_id', User::tableName(), 'id', 'SET NULL');

        $this->createTable(File::tableName(), [
            'id' => $this->primaryKey()->unsigned(),
            'status' => $this->tinyInteger(1)->unsigned()->notNull()->defaultValue(File::STATUS_ENABLED),
            'folder_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(File::BASENAME_MAX_LENGTH)->notNull(),
            'basename' => $this->string(File::BASENAME_MAX_LENGTH)->notNull(),
            'extension' => $this->string(20)->notNull(),
            'width' => $this->smallInteger()->unsigned()->null(),
            'height' => $this->smallInteger()->unsigned()->null(),
            'size' => $this->bigInteger()->unsigned()->notNull()->defaultValue(0),
            'transformation_count' => $this->smallInteger()->notNull()->defaultValue(0),
            'updated_by_user_id' => $this->integer()->unsigned()->null(),
            'updated_at' => $this->dateTime(),
            'created_at' => $this->dateTime()->notNull(),
        ], $this->getTableOptions());

        $this->createIndex('folder_id', File::tableName(), 'folder_id');

        $tableName = $schema->getRawTableName(File::tableName());
        $this->addForeignKey($tableName . '_updated_by_ibfk', File::tableName(), 'updated_by_user_id', User::tableName(), 'id', 'SET NULL');
        $this->addForeignKey($tableName . '_folder_id_ibfk', File::tableName(), 'folder_id', Folder::tableName(), 'id', 'CASCADE');

        $auth = Yii::$app->getAuthManager();
        $admin = $auth->getRole(User::AUTH_ROLE_ADMIN);

        $upload = $auth->createRole('upload');
        $auth->add($upload);

        $auth->addChild($admin, $upload);
    }

    public function safeDown(): void
    {
        $this->dropTable(File::tableName());
        $this->dropTable(Folder::tableName());

        $auth = Yii::$app->getAuthManager();
        $this->delete($auth->itemTable, ['name' => 'upload']);

        $auth->invalidateCache();
    }
}
