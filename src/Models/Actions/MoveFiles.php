<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Yii;

/**
 * Moving a file renames it on disk and drops its transformations, so a batch is not wrapped in a transaction: a
 * rollback would leave the files where the filesystem put them while the records claim otherwise. A failed file is
 * collected instead, and the caller reports what got through.
 */
class MoveFiles
{
    private int $movedCount = 0;

    /**
     * @var File[]
     */
    private array $renamed = [];

    /**
     * @var File[]
     */
    private array $failed = [];

    /**
     * @param File[] $files
     */
    public function __construct(
        protected array $files,
        protected Folder $folder,
    ) {
    }

    public function run(): bool
    {
        $folderIds = [];

        foreach ($this->files as $file) {
            if ($file->folder_id === $this->folder->id) {
                continue;
            }

            $folderIds[$file->folder_id] = $file->folder_id;
            $basename = $file->basename;

            // The file counts of both folders are recalculated once, after every file was moved.
            $file->setIsBatch(true);
            $file->populateFolderRelation($this->folder);

            if ($file->update() === false) {
                $this->failed[] = $file;
                continue;
            }

            $this->movedCount++;

            if ($file->basename !== $basename) {
                $this->renamed[] = $file;
            }
        }

        if ($this->movedCount) {
            $folderIds[$this->folder->id] = $this->folder->id;
            $this->updateFileCounts($folderIds);
        }

        return !$this->failed;
    }

    /**
     * @param int[] $folderIds
     */
    protected function updateFileCounts(array $folderIds): void
    {
        foreach (Folder::findAll(['id' => $folderIds]) as $folder) {
            $folder->recalculateFileCount()->update();
        }
    }

    public function getMovedCount(): int
    {
        return $this->movedCount;
    }

    /**
     * @return File[] the files a name collision in the target folder renamed
     */
    public function getRenamed(): array
    {
        return $this->renamed;
    }

    /**
     * @return File[]
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    /**
     * @param File[] $files
     */
    public static function create(array $files, Folder $folder): static
    {
        $action = Yii::createObject(static::class, [$files, $folder]);
        $action->run();

        return $action;
    }
}
