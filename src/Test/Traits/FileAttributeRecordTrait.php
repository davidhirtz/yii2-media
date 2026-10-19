<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Traits;

use Hirtz\Media\Test\Models\FileAttributeRecord;
use Yii;

/**
 * The table of {@see FileAttributeRecord} and the helpers its tests share.
 */
trait FileAttributeRecordTrait
{
    protected function createFileAttributeRecordTable(): void
    {
        Yii::$app->getDb()->createCommand()
            ->createTable(FileAttributeRecord::tableName(), [
                'id' => 'pk',
                'name' => 'string null',
                'custom_attributes' => 'json null',
                'updated_at' => 'datetime null',
            ])
            ->execute();
    }

    protected function dropFileAttributeRecordTable(): void
    {
        Yii::$app->getDb()->createCommand()
            ->dropTable(FileAttributeRecord::tableName())
            ->execute();
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function createFileAttributeRecord(array $values = [], string $name = 'Å'): FileAttributeRecord
    {
        $record = FileAttributeRecord::create();
        $record->name = $name;
        $record->setAttributes($values, false);

        self::assertTrue($record->insert(), implode(' ', $record->getErrorSummary(true)));

        return $record;
    }

    protected function findFileAttributeRecord(int $id): FileAttributeRecord
    {
        return FileAttributeRecord::find()->where(['id' => $id])->one() ?? self::fail('The record was not found.');
    }
}
