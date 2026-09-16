<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Traits;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Helpers\FileHelper;

/**
 * A test needing a real file on disk writes a placeholder for it, since the model opens an upload to read and
 * rotate it.
 */
trait MediaFileTrait
{
    protected Folder $folder;

    /**
     * @param class-string<Folder> $model
     */
    protected function createFolder(string $name, string $path, string $model = Folder::class): Folder
    {
        $folder = $model::create();
        $folder->loadDefaultValues();
        $folder->name = $name;
        $folder->path = $path;

        self::assertTrue($folder->insert(), print_r($folder->getErrors(), true));

        FileHelper::createDirectory($folder->getUploadPath());

        return $folder;
    }

    /**
     * @param class-string<File> $model
     */
    protected function buildFile(
        string $basename,
        string $extension = 'jpg',
        int $width = 100,
        int $height = 100,
        ?Folder $folder = null,
        string $model = File::class,
    ): File {
        $file = $model::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($basename);
        $file->basename = $basename;
        $file->extension = $extension;
        $file->width = $width;
        $file->height = $height;
        $file->populateFolderRelation($folder ?? $this->folder);

        return $file;
    }

    /**
     * @param class-string<File> $model
     */
    protected function createFile(
        string $basename,
        string $extension = 'jpg',
        int $width = 100,
        int $height = 100,
        ?Folder $folder = null,
        string $model = File::class,
    ): File {
        $folder ??= $this->folder;
        $path = $folder->getUploadPath() . "$basename.$extension";
        $this->writeImage($path, $width, $height);

        $file = $this->buildFile($basename, $extension, $width, $height, $folder, $model);
        $file->size = filesize($path) ?: 0;

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        // a colliding basename is numbered on save, so the placeholder has to follow the name the record kept
        if (!is_file($file->getFilePath())) {
            $this->writeImage($file->getFilePath(), $width, $height);
        }

        return $file;
    }

    protected function writeImage(string $path, int $width = 100, int $height = 100): void
    {
        FileHelper::createDirectory(dirname($path));

        if (pathinfo($path, PATHINFO_EXTENSION) === 'svg') {
            file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
            return;
        }

        $image = imagecreatetruecolor(max($width, 1), max($height, 1));
        imagejpeg($image, $path);
    }
}
