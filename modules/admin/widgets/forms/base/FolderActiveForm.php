<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\base;

use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

/**
 * Class FolderActiveForm.
 * @package davidhirtz\yii2\media\modules\admin\widgets\forms\base
 *
 * @property FolderForm $model
 */
class FolderActiveForm extends ActiveForm
{
    use ModuleTrait;

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
                ['type', 'dropDownList', ArrayHelper::getColumn(FolderForm::getTypes(), 'name')],
                ['name'],
                ['path', 'url'],
            ];
        }

        parent::init();
    }

    /**
     * @param mixed $attribute can be used to customize the base url per attribute
     * @return bool|string
     */
    public function getBaseUrl($attribute = null)
    {
        return \Yii::getAlias(static::getModule()->uploadPath) . '/';
    }
}