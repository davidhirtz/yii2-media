<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;

/**
 * Class FolderActiveForm.
 * @package davidhirtz\yii2\media\modules\admin\widgets\forms
 *
 * @property Folder $model
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
                ['type', 'dropDownList', ArrayHelper::getColumn(Folder::getTypes(), 'name')],
                ['name'],
                ['path'],
            ];
        }

        parent::init();
    }

    /**
     * @return \davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveField
     */
    public function pathField(): string
    {
        return $this->field($this->model, 'path')->slug(['baseUrl' => \Yii::getAlias(static::getModule()->uploadPath)]);
    }
}