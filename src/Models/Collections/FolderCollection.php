<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Collections;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\modules\ModuleTrait;
use Yii;
use yii\caching\TagDependency;

/**
 * @template T of Folder
 */
class FolderCollection
{
    use ModuleTrait;

    public const string CACHE_KEY = 'folder-collection';

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
        TagDependency::invalidate(Yii::$app->getCache(), static::CACHE_KEY);

        self::$_default = null;
        self::$_folders = null;
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
