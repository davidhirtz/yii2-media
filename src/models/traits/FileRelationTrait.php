<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\models\traits;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\queries\FileQuery;

/**
 * @property-read File|null $file {@see static::populateFileRelation()}
 */
trait FileRelationTrait
{
    public function getFile(): FileQuery
    {
        /** @var FileQuery $relation */
        $relation = $this->hasOne(File::class, ['id' => 'file_id']);
        return $relation;
    }

    public function populateFileRelation(?File $file): void
    {
        $this->populateRelation('file', $file);
        $this->file_id = $file?->id;
    }
}
