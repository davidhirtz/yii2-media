<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\nav\base;

use davidhirtz\yii2\media\modules\admin\widgets\FileLinkButtonTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Toolbar;

/**
 * Class FileToolbar
 * @package davidhirtz\yii2\media\modules\admin\widgets\nav\base
 * @see \davidhirtz\yii2\media\modules\admin\widgets\panels\FileToolbar
 */
class FileToolbar extends Toolbar
{
    use FileLinkButtonTrait;

    /**
     * @inheritDoc
     */
    public function init()
    {
        if ($this->actions === null) {
            $this->actions = $this->hasForm() ? [$this->getFormSubmitButton()] : [];
        }

        if ($this->links === null) {
            $this->links = $this->hasForm() ? [$this->getFileLinkButton()] : [];
        }

        parent::init();
    }
}