<?php

namespace davidhirtz\yii2\media\migrations;

use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\db\MigrationTrait;
use yii\db\Migration;

/**
 * Class M190322214201Transformation
 */
class M190322214201Transformation extends Migration
{
    use MigrationTrait;

    /**
     * {@inheritdoc}
     */
    public function safeUp()
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

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable(Transformation::tableName());
    }
}
