<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Collections;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\ModuleTrait;
use Yii;
use yii\caching\TagDependency;

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

    /**
     * @return Folder|null
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

    /**
     * @return array<int, Folder>
     */
    protected static function findAll(): array
    {
        return Folder::find()
            ->select(['id', 'name', 'path'])
            ->orderBy(static::getModule()->defaultFolderOrder)
            ->indexBy('id')
            ->all();
    }

    /**
     * @return Folder
     */
    public static function getDefault(): Folder
    {
        self::$default ??= Folder::find()
            ->orderBy(self::getModule()->defaultFolderOrder)
            ->limit(1)
            ->one();

        if (!self::$default) {
            $folder = Folder::create();
            $folder->type = Folder::TYPE_DEFAULT;
            $folder->name = Yii::t('media', 'FOLDER_DEFAULT');
            $folder->save();

            // the save invalidates the collection, which clears the static again, so it is assigned afterwards
            self::$default = $folder;
        }

        return self::$default;
    }

    public static function invalidateCache(): void
    {
        TagDependency::invalidate(Yii::$app->getCache(), static::CACHE_KEY);
        self::reset();
    }

    /**
     * The static outlives the application; `Bootstrap` resets it, so a test's application does not start with the
     * folders of the one before.
     */
    public static function reset(): void
    {
        self::$default = null;
        self::$folders = null;
    }
}
