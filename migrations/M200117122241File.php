<?php

namespace davidhirtz\yii2\media\migrations;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\db\MigrationTrait;
use yii\db\Migration;

/**
 * Class M200117122241File
 */
class M200117122241File extends Migration
{
    use MigrationTrait;

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $file = File::instance();
        $after = 'size';

        foreach ($file->getI18nAttributeNames('alt_text') as $attributeName) {
            $this->addColumn(File::tableName(), $attributeName, $this->string(250)->null()->after($after));
            $after = $attributeName;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $file = File::instance();
        foreach ($file->getI18nAttributeNames('alt_text') as $attributeName) {
            $this->dropColumn(File::tableName(), $attributeName);
        }
    }
}
