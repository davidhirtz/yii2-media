<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Yii;

/**
 * Not wrapped in a transaction, since a delete unlinks the file on disk and a rollback would keep a record without it.
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
            File::getModule()->invalidatePageCache();
        }

        return !$this->failed;
    }

    /**
     * A folder being deleted itself is not recounted, since its row is about to go ({@see Folder::beforeDelete()}).
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
