<?php

declare(strict_types=1);

namespace Hirtz\Media\migrations;

use Hirtz\Media\models\File;
use Hirtz\Media\models\Transformation;
use Hirtz\Skeleton\db\traits\MigrationTrait;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M190322214201Transformation extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $this->createTable(Transformation::tableName(), [
            'id' => $this->primaryKey()->unsigned(),
            'file_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(50)->notNull(),
            'extension' => $this->string(20)->notNull(),
            'width' => $this->smallInteger()->null(),
            'height' => $this->smallInteger()->null(),
            'size' => $this->bigInteger()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ], $this->getTableOptions());

        $this->createIndex('file_id', Transformation::tableName(), 'file_id');
        $this->addForeignKey('transformation_file_id_ibfk', Transformation::tableName(), 'file_id', File::tableName(), 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable(Transformation::tableName());
    }
}
