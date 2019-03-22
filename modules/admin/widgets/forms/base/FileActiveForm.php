<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\base;

use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use yii\helpers\Html;

/**
 * Class FileActiveForm.
 * @package davidhirtz\yii2\media\modules\admin\widgets\forms\base
 *
 * @property FileForm $model
 */
class FileActiveForm extends ActiveForm
{
    /**
     * @var bool
     */
    public $showUnsafeAttributes = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!$this->fields) {
            $this->fields = [
                ['thumbnail'],
                ['-'],
                ['name'],
                ['filename'],
            ];
        }


        parent::init();
    }

    /**
     * @return string
     */
    public function thumbnailField()
    {
        return $this->model->hasThumbnail() ? $this->row($this->offset(Html::img($this->model->folder->getUploadUrl() . $this->model->filename))) : '';
    }
}