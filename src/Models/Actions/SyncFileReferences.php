<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileReference;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use Yii;

class SyncFileReferences
{
    /**
     * @var list<int>
     */
    protected array $fileIds = [];

    public function __construct(protected readonly ActiveRecord&CustomAttributeInterface $owner)
    {
        $this->syncReferences();
        $this->updateReferenceCounts();
    }

    protected function syncReferences(): void
    {
        $wanted = $this->owner->isDeleted()
            ? []
            : $this->owner->getCustomAttributeRecordIds(FileCustomAttribute::class);

        $references = FileReference::find()
            ->where([
                'model_class' => $this->owner::class,
                'model_id' => $this->owner->getPrimaryKey(),
            ])
            ->indexBy('file_id')
            ->all();

        // An id a write outside `save()` left behind names no file, and the foreign key would refuse its row.
        $existing = $wanted
            ? array_map(intval(...), File::find()->select('id')->where(['id' => array_keys($wanted)])->column())
            : [];

        foreach ($wanted as $fileId => $counts) {
            if (!in_array($fileId, $existing, true)) {
                continue;
            }

            $reference = $references[$fileId] ?? null;
            unset($references[$fileId]);

            $useCount = array_sum($counts);

            if ($reference && $reference->use_count === $useCount && $this->getSortedCounts($reference) === $counts) {
                continue;
            }

            $reference ??= FileReference::create();
            $reference->setIsBatch(true);
            $reference->setAttributes([
                'file_id' => $fileId,
                'model_class' => $this->owner::class,
                'model_id' => $this->owner->getPrimaryKey(),
                'use_count' => $useCount,
                'attribute_counts' => $counts,
            ], false);

            $reference->save(false);
            $this->fileIds[] = $fileId;
        }

        foreach ($references as $reference) {
            $reference->setIsBatch(true);
            $reference->delete();

            $this->fileIds[] = $reference->file_id;
        }
    }

    protected function updateReferenceCounts(): void
    {
        if (!$this->fileIds) {
            return;
        }

        $files = File::find()
            ->where(['id' => array_values(array_unique($this->fileIds))])
            ->all();

        foreach ($files as $file) {
            $file->updateReferenceCount();
        }
    }

    /**
     * @return array<string, int>
     */
    private function getSortedCounts(FileReference $reference): array
    {
        $counts = array_map(intval(...), $reference->attribute_counts ?? []);
        ksort($counts);

        return $counts;
    }

    public static function create(ActiveRecord&CustomAttributeInterface $owner): void
    {
        Yii::createObject(static::class, [$owner]);
    }
}
