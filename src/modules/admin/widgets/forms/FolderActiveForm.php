<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\modules\admin\widgets\forms\traits\ModelTimestampTrait;
use davidhirtz\yii2\skeleton\modules\admin\widgets\forms\traits\TypeFieldTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use yii\widgets\ActiveField;

/**
 * @property Folder $model
 */
class FolderActiveForm extends ActiveForm
{
    use ModuleTrait;
    use ModelTimestampTrait;
    use TypeFieldTrait;

    #[\Override]
    public function init(): void
    {
        $this->fields ??= [
            'type',
            'name',
            'path',
        ];

        parent::init();
    }

    /**
     * @noinspection PhpUnused {@see static::$fields}
     */
    public function pathField(array $options = []): ActiveField|string
    {
        if ($this->model->getIsNewRecord() || static::getModule()->enableRenameFolders) {
            $options['baseUrl'] ??= static::getModule()->baseUrl;
            return $this->field($this->model, 'path')->slug($options);
        }

        return '';
    }
}
