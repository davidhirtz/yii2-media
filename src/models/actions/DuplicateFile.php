<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\models\actions;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\models\actions\DuplicateActiveRecord;
use Yii;

/**
 * @extends DuplicateActiveRecord<File>
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
        $copySuccessful = $this->duplicate->copy($this->model->getFilePath());

        if (!$copySuccessful) {
            $this->duplicate->addError('upload', Yii::t('yii', 'File upload failed.'));
            return false;
        }

        return true;
    }
}
