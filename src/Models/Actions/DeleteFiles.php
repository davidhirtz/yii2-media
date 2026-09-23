<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Yii;

/**
 * Deleted one at a time and the folders recounted once at the end, so a selection spanning fifty files of the
 * same folder costs one recalculation rather than fifty. A file that fails is reported and the rest are kept.
 *
 * Deliberately not wrapped in a transaction: a delete unlinks the file on disk, so a rollback would leave the
 * record claiming a file the filesystem no longer has.
 */
class DeleteFiles
{
    /**
     * @var list<File>
     */
    private array $deleted = [];

    /**
     * @var list<File>
     */
    private array $failed = [];

    /**
     * @param File[] $files
     */
    public function __construct(protected array $files)
    {
    }

    public function run(): bool
    {
        foreach ($this->files as $file) {
            $file->setIsBatch(true);

            if ($file->delete() === false) {
                $this->failed[] = $file;
                continue;
            }

            $this->deleted[] = $file;
        }

        if ($this->deleted) {
            $this->updateFileCounts();
        }

        return !$this->failed;
    }

    /**
     * A folder that is being deleted itself is left alone: its row is still there while
     * {@see Folder::beforeDelete()} runs, and recounting it would only write to a row about to go.
     */
    protected function updateFileCounts(): void
    {
        $folderIds = [];

        foreach ($this->deleted as $file) {
            if ($file->folder && !$file->folder->isDeleted()) {
                $folderIds[$file->folder_id] = $file->folder_id;
            }
        }

        if (!$folderIds) {
            return;
        }

        foreach (Folder::findAll(['id' => $folderIds]) as $folder) {
            $folder->updateFileCount();
        }
    }

    /**
     * @return list<File>
     */
    public function getDeleted(): array
    {
        return $this->deleted;
    }

    /**
     * @return list<File>
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    /**
     * @param File[] $files
     */
    public static function create(array $files): static
    {
        $action = Yii::createObject(static::class, [$files]);
        $action->run();

        return $action;
    }
}
