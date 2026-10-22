<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\File;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Web\CopiedUploadedFile;
use Hirtz\Skeleton\Models\Actions\DuplicateActiveRecord;
use Override;
use Yii;

/**
 * @extends DuplicateActiveRecord<File>
 */
class DuplicateFile extends DuplicateActiveRecord
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(File $file, array $attributes = [])
    {
        parent::__construct($file, $attributes);
    }

    #[Override]
    protected function beforeDuplicate(): bool
    {
        $this->prefixDuplicateName(maxLength: File::BASENAME_MAX_LENGTH);

        if (!parent::beforeDuplicate()) {
            return false;
        }

        $this->duplicate->populateFolderRelation($this->model->folder);
        $copySuccessful = $this->duplicate->copy($this->model->getFilePath());

        if (!$copySuccessful) {
            $this->duplicate->addError('upload', Yii::t('yii', 'File upload failed.'));
            return false;
        }

        $this->copyPreview();

        return true;
    }

    /**
     * The preview of a file no image library reads was drawn by the browser at upload, and cannot be drawn again.
     */
    protected function copyPreview(): void
    {
        if ($this->model->isTransformableImage()) {
            return;
        }

        $preview = $this->model->getTransformations()
            ->andWhere(['name' => Transformation::NAME_ADMIN])
            ->one();

        if ($preview) {
            $this->duplicate->preview = new CopiedUploadedFile(['path' => $preview->getFilePath()]);
        }
    }
}
