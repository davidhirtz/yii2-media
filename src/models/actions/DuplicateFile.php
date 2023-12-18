<?php

namespace davidhirtz\yii2\media\models\actions;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\models\actions\DuplicateActiveRecord;

/**
 * @template-extends DuplicateActiveRecord<File>
 */
class DuplicateFile extends DuplicateActiveRecord
{
    public function __construct(File $file, array $attributes = [])
    {
        parent::__construct($file, $attributes);
    }

    protected function beforeDuplicate(): bool
    {
        if (!parent::beforeDuplicate()) {
            return false;
        }

        $this->duplicate->populateFolderRelation($this->model->folder);
        $this->duplicate->copy($this->model->getFilePath());

        return true;
    }
}
