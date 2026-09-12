<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Collections;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\ModuleTrait;
use Yii;
use yii\caching\TagDependency;

/**
 * @template T of Folder
 */
class FolderCollection
{
    use ModuleTrait;

    public const string CACHE_KEY = 'folder-collection';

    protected static ?array $folders = null;
    protected static ?Folder $default = null;

    /**
     * @return array<int, T>
     */
    public static function getAll(bool $refresh = false): array
    {
        if (null === static::$folders || $refresh) {
            $dependency = new TagDependency(['tags' => static::CACHE_KEY]);
            $duration = static::getModule()->folderCachedQueryDuration;

            static::$folders = $duration !== false
                ? Folder::getDb()->cache(static::findAll(...), $duration, $dependency)
                : static::findAll();
        }

        return static::$folders;
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

    /**
     * @return T
     */
    public static function getDefault(): Folder
    {
        self::$default ??= Folder::find()
            ->orderBy(self::getModule()->defaultFolderOrder)
            ->limit(1)
            ->one();

        if (!self::$default) {
            self::$default = Folder::create();
            self::$default->type = Folder::TYPE_DEFAULT;
            self::$default->name = Yii::t('media', 'FOLDER_DEFAULT');
            self::$default->save();

        }

        return self::$default;
    }

    public static function invalidateCache(): void
    {
        TagDependency::invalidate(Yii::$app->getCache(), static::CACHE_KEY);
    }

    public static function reset(): void
    {
        self::$default = null;
        self::$folders = null;

        self::invalidateCache();
    }
}
