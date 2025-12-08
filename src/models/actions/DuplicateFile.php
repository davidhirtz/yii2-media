<?php

declare(strict_types=1);

namespace Hirtz\Media\models\actions;

use Hirtz\Media\models\File;
use Hirtz\Skeleton\models\actions\DuplicateActiveRecord;
use Override;
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

    #[Override]
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
