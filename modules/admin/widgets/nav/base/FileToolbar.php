<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\nav\base;

use davidhirtz\yii2\media\modules\admin\widgets\FileLinkButtonTrait;
use davidhirtz\yii2\media\modules\admin\widgets\UploadTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Toolbar;
use Yii;

/**
 * Class FileToolbar
 * @package davidhirtz\yii2\media\modules\admin\widgets\nav\base
 * @see \davidhirtz\yii2\media\modules\admin\widgets\panels\FileToolbar
 */
class FileToolbar extends Toolbar
{
    use FileLinkButtonTrait;
    use UploadTrait;

    /**
     * @inheritDoc
     */
    public function init()
    {
        if ($this->actions === null) {
            $this->actions = $this->hasForm() ? [$this->getFormSubmitButton()] : [$this->getUploadFileButton(), $this->getImportFileButton()];
        }

        if ($this->links === null) {
            $this->links = $this->hasForm() ? [$this->getFileLinkButton()] : [];
        }

        parent::init();
    }

    /**
     * @return array
     */
    protected function getCreateRoute()
    {
        return ['create', 'folder' => Yii::$app->getRequest()->get('folder')];
    }
}