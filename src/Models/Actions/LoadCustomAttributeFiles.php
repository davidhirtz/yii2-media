<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Models\CustomAttributes\GroupCustomAttribute;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use yii\base\Model;

/**
 * Loads the files the {@see FileCustomAttribute}s of several models name in one query.
 */
class LoadCustomAttributeFiles
{
    /**
     * @param list<Model> $models
     * @return list<int>
     */
    public static function getFileIds(array $models): array
    {
        $ids = [];

        foreach ($models as $model) {
            if ($model instanceof CustomAttributeInterface) {
                $ids = [...$ids, ...array_keys($model->getCustomAttributeRecordIds(FileCustomAttribute::class))];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param list<Model> $models
     * @param array<int, File> $files
     */
    public static function populate(array $models, array $files): void
    {
        foreach ($models as $model) {
            if (!$model instanceof CustomAttributeInterface) {
                continue;
            }

            foreach ($model->getCustomAttributeDefinitions() as $definition) {
                foreach ($definition->getAttributeNames($model) as $name) {
                    if ($definition instanceof FileCustomAttribute) {
                        $id = $definition->normalize($model->$name);

                        if ($id !== null) {
                            $model->populateCustomAttributeRecord($name, $files[$id] ?? null);
                        }
                    } elseif ($definition instanceof GroupCustomAttribute) {
                        static::populate(array_values($model->getCustomAttributeItems($name)), $files);
                    }
                }
            }
        }
    }

    /**
     * For models rendered outside a preload, such as a teaser list.
     *
     * @param list<Model> $models
     * @return array<int, File>
     */
    public static function create(array $models): array
    {
        $files = static::findFiles(static::getFileIds($models));
        static::populate($models, $files);

        return $files;
    }

    /**
     * @param list<int> $ids
     * @return array<int, File>
     */
    public static function findFiles(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $files = File::find()
            ->selectSiteAttributes()
            ->withTranslations()
            ->where(['id' => $ids])
            ->indexBy('id')
            ->all();

        $folders = FolderCollection::getAll();

        foreach ($files as $file) {
            $file->populateFolderRelation($folders[$file->folder_id] ?? null);
        }

        return $files;
    }
}
