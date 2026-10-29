<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use Override;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M261020100000FileReference extends Migration
{
    use MigrationTrait;

    #[Override]
    public function safeUp(): void
    {
        $this->createTable('{{%file_reference}}', [
            'id' => $this->primaryKey()->unsigned(),
            'file_id' => $this->integer()->unsigned()->notNull(),
            'model_class' => $this->string(255)->notNull(),
            'model_id' => $this->integer()->unsigned()->notNull(),
            'use_count' => $this->smallInteger()->unsigned()->notNull()->defaultValue(0),
            'attribute_counts' => $this->json()->null(),
            'updated_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], $this->getTableOptions());

        $this->createIndex('model_file', '{{%file_reference}}', ['model_class', 'model_id', 'file_id'], true);
        $this->createIndex('file_id', '{{%file_reference}}', 'file_id');

        $this->addForeignKey(
            'file_reference_file_id_ibfk',
            '{{%file_reference}}',
            'file_id',
            '{{%file}}',
            'id',
            'CASCADE',
        );

        $this->addColumn(
            '{{%file}}',
            'reference_count',
            (string)$this->integer()->unsigned()->notNull()->defaultValue(0)->after('asset_count'),
        );
    }

    #[Override]
    public function safeDown(): void
    {
        $this->dropColumn('{{%file}}', 'reference_count');
        $this->dropTable('{{%file_reference}}');
    }
}
