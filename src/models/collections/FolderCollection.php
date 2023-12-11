<?php

namespace davidhirtz\yii2\media\models\collections;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use Yii;
use yii\caching\TagDependency;

class FolderCollection
{
    use ModuleTrait;

    public const CACHE_KEY = 'folder-collection';

    private static ?array $_folders = null;

    /**
     * @return array<int, Folder>
     */
    public static function getAll(bool $refresh = false): array
    {
        if (null === static::$_folders || $refresh) {
            $dependency = new TagDependency(['tags' => static::CACHE_KEY]);
            $duration = static::getModule()->folderCachedQueryDuration;

            static::$_folders = $duration !== false
                ? Yii::$app->getDb()->cache(static::findAll(...), $duration, $dependency)
                : static::findAll();
        }

        return static::$_folders;
    }

    public static function getByPath(string $path): ?Folder
    {
        foreach (static::getAll() as $folder) {
            if ($folder->path === $path) {
                return $folder;
            }
        }

        return null;
    }

    public static function findAll(): array
    {
        return Folder::find()
            ->select(['id', 'name', 'path'])
            ->orderBy(static::getModule()->defaultFolderOrder)
            ->indexBy('id')
            ->all();
    }

    public static function invalidateCache(): void
    {
        if (static::getModule()->folderCachedQueryDuration !== false) {
            TagDependency::invalidate(Yii::$app->getCache(), static::CACHE_KEY);
        }
    }
}