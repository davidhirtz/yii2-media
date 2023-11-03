<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\modules\admin\widgets\forms\traits\ModelTimestampTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use davidhirtz\yii2\skeleton\widgets\forms\DynamicRangeDropdown;
use yii\widgets\ActiveField;

/**
 * @property Folder $model
 */
class FolderActiveForm extends ActiveForm
{
    use ModuleTrait;
    use ModelTimestampTrait;

    public function init(): void
    {
        $this->fields ??= [
            $this->typeField(...),
            'name',
            $this->pathField(...),
        ];

        parent::init();
    }

    public function pathField(array $options = []): ActiveField|string
    {
        if ($this->model->getIsNewRecord() || static::getModule()->enableRenameFolders) {
            $options['baseUrl'] ??= static::getModule()->baseUrl;
            return $this->field($this->model, 'path')->slug($options);
        }

        return '';
    }

    public function typeField(array $options = []): ActiveField|string
    {
        return $this->field($this->model, 'type')->widget(DynamicRangeDropdown::class, $options);
    }

    public function renderFooter(): void
    {
        if ($items = array_filter($this->getFooterItems())) {
            echo $this->listRow($items);
        }
    }

    protected function getFooterItems(): array
    {
        return $this->getTimestampItems();
    }
}