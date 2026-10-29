<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Collections;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\ModuleTrait;
use Yii;
use Hirtz\Skeleton\Caching\TagDependency;

class FolderCollection
{
    use ModuleTrait;

    public const string CACHE_KEY = 'folder-collection';

    /**
     * @var array<int, Folder>|null
     */
    protected static ?array $folders = null;
    protected static ?Folder $default = null;

    /**
     * @return array<int, Folder>
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

    public static function getByPath(string $path): ?Folder
    {
        foreach (static::getAll() as $folder) {
            if ($folder->path === $path) {
                return $folder;
            }
        }

        return null;
    }

    /**
     * @return array<int, Folder>
     */
    public static function getByType(int $type): array
    {
        return array_filter(static::getAll(), fn (Folder $folder): bool => $folder->type === $type);
    }

    /**
     * @return array<int, Folder>
     */
    protected static function findAll(): array
    {
        return Folder::find()
            ->select(['id', 'type', 'name', 'path'])
            ->orderBy(static::getModule()->defaultFolderOrder)
            ->indexBy('id')
            ->all();
    }

    public static function getDefault(): Folder
    {
        self::$default ??= Folder::find()
            ->orderBy(self::getModule()->defaultFolderOrder)
            ->limit(1)
            ->one();

        if (!self::$default) {
            $folder = Folder::instantiateByType(Folder::TYPE_DEFAULT);
            $folder->name = Yii::t('media', 'FOLDER_DEFAULT_NAME');
            $folder->save();

            // the save invalidates the collection, which clears the static again, so it is assigned afterwards
            self::$default = $folder;
        }

        return self::$default;
    }

    /**
     * A default folder already named after the default label is not marked again, which would read "Default (Default)".
     */
    public static function isMarkedAsDefault(Folder $folder): bool
    {
        return $folder->id === static::getDefault()->id
            && mb_strtolower($folder->name) !== mb_strtolower(Yii::t('media', 'FOLDER_DEFAULT_NAME'));
    }

    public static function invalidateCache(): void
    {
        TagDependency::invalidate(Yii::$app->getCache(), static::CACHE_KEY);
        self::reset();
    }

    /**
     * The static outlives the application, so `Bootstrap` resets it.
     */
    public static function reset(): void
    {
        self::$default = null;
        self::$folders = null;
    }
}
