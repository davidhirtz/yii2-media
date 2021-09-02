<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\modules\admin\widgets\forms\traits\ModelTimestampTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use davidhirtz\yii2\skeleton\widgets\forms\DynamicRangeDropdown;
use yii\widgets\ActiveField;

/**
 * Class FolderActiveForm
 * @package davidhirtz\yii2\media\modules\admin\widgets\forms
 *
 * @property Folder $model
 */
class FolderActiveForm extends ActiveForm
{
    use ModuleTrait;
    use ModelTimestampTrait;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!$this->fields) {
            $this->fields = [
                'type',
                'name',
                'path',
            ];
        }

        parent::init();
    }

    /**
     * @return ActiveField|string
     */
    public function pathField(): string
    {
        if ($this->model->getIsNewRecord() || static::getModule()->enableRenameFolders) {
            return $this->field($this->model, 'path')->slug([
                'baseUrl' => static::getModule()->baseUrl,
            ]);
        }

        return '';
    }

    /**
     * @param array $options
     * @return ActiveField|string
     */
    public function typeField($options = [])
    {
        return $this->field($this->model, 'type')->widget(DynamicRangeDropdown::class, $options);
    }

    /**
     * Renders user information footer.
     */
    public function renderFooter()
    {
        if ($items = array_filter($this->getFooterItems())) {
            echo $this->listRow($items);
        }
    }

    /**
     * @return array
     */
    protected function getFooterItems(): array
    {
        return $this->getTimestampItems();
    }
}