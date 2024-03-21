<?php

namespace davidhirtz\yii2\media\models\collections;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use Yii;
use yii\caching\TagDependency;

/**
 * @template T of Folder
 */
class FolderCollection
{
    use ModuleTrait;

    public const CACHE_KEY = 'folder-collection';

    protected static ?array $_folders = null;
    protected static ?Folder $_default = null;

    /**
     * @return array<int, T>
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

    /**
     * @return T|null
     */
    public static function getByPath(string $path): ?Folder
    {
        foreach (static::getAll() as $folder) {
            if ($folder->path === $path) {
                return $folder;
            }
        }

        return null;
    }

    protected static function findAll(): array
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

    /**
     * @return T
     */
    public static function getDefault(): Folder
    {
        self::$_default ??= Folder::find()
            ->orderBy(self::getModule()->defaultFolderOrder)
            ->limit(1)
            ->one();

        if (!self::$_default) {
            self::$_default = Folder::create();
            self::$_default->type = Folder::TYPE_DEFAULT;
            self::$_default->name = Yii::t('media', 'Default');
            self::$_default->save();
        }

        return self::$_default;
    }
}
