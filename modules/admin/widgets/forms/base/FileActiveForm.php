<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\base;

use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use davidhirtz\yii2\skeleton\widgets\forms\CKEditor;
use Yii;
use yii\helpers\ArrayHelper;
use yii\jui\DatePicker;
use yii\web\JsExpression;

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
        return $this->model->cloudinary_id ? $this->row($this->offset(Html::img($this->model->getPhotoUrl(), ['alt' => $this->model->getOldAttribute('name'), 'width' => 120]))) : '';
    }
}