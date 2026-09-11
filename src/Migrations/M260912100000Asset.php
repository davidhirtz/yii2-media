<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use Hirtz\Skeleton\Models\User;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M260912100000Asset extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $this->createTable(Asset::tableName(), [
            'id' => $this->primaryKey()->unsigned(),
            'status' => $this->tinyInteger(1)->unsigned()->notNull()->defaultValue(Asset::STATUS_ENABLED),
            'type' => $this->smallInteger()->notNull()->defaultValue(Asset::TYPE_DEFAULT),
            'model_class' => $this->string()->notNull(),
            'model_id' => $this->bigInteger()->unsigned()->notNull(),
            'file_id' => $this->integer()->unsigned()->notNull(),
            'position' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'updated_by_user_id' => $this->integer()->unsigned()->null(),
            'updated_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], $this->getTableOptions());

        $this->addCustomAttributesColumn(Asset::tableName());

        $this->createIndex('model_class', Asset::tableName(), ['model_class', 'model_id', 'status', 'position']);
        $this->createIndex('file_id', Asset::tableName(), ['file_id']);

        $this->addForeignKey(
            $this->getForeignKeyName(Asset::tableName(), 'file_id') . '_ibfk',
            Asset::tableName(),
            'file_id',
            File::tableName(),
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->getForeignKeyName(Asset::tableName(), 'updated_by_user_id') . '_ibfk',
            Asset::tableName(),
            'updated_by_user_id',
            User::tableName(),
            'id',
            'SET NULL'
        );

        $this->addColumn(File::tableName(), 'asset_count', (string)$this->smallInteger()
            ->notNull()
            ->defaultValue(0)
            ->after('transformation_count'));
    }

    public function safeDown(): void
    {
        $this->dropColumn(File::tableName(), 'asset_count');
        $this->dropTable(Asset::tableName());
    }
}
