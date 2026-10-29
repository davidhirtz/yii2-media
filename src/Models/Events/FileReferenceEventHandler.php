<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Events;

use Hirtz\Media\Models\Actions\SyncFileReferences;
use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\CustomAttributes\CustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\GroupCustomAttribute;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use yii\db\AfterSaveEvent;

/**
 * Registered on every record, so it returns at once unless a file custom attribute is there and, on update, changed.
 */
class FileReferenceEventHandler
{
    public static function handleAfterSave(ActiveRecord $record, AfterSaveEvent $event): void
    {
        if (!$record instanceof CustomAttributeInterface) {
            return;
        }

        foreach (array_keys($event->changedAttributes) as $name) {
            $definition = $record->getCustomAttribute((string)$name);

            if ($definition !== null && static::holdsFiles($definition)) {
                SyncFileReferences::create($record);
                return;
            }
        }
    }

    public static function handleAfterDelete(ActiveRecord $record): void
    {
        if (!$record instanceof CustomAttributeInterface) {
            return;
        }

        foreach ($record->getCustomAttributeDefinitions() as $definition) {
            if (static::holdsFiles($definition)) {
                SyncFileReferences::create($record);
                return;
            }
        }
    }

    public static function holdsFiles(CustomAttribute $definition): bool
    {
        if ($definition instanceof FileCustomAttribute) {
            return true;
        }

        if ($definition instanceof GroupCustomAttribute) {
            foreach ($definition->getAttributes() as $attribute) {
                if (static::holdsFiles($attribute)) {
                    return true;
                }
            }
        }

        return false;
    }
}
