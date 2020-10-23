<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\modules\admin\widgets\forms\ModelTimestampTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;
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
                ['type', 'dropDownList', ArrayHelper::getColumn(Folder::getTypes(), 'name')],
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