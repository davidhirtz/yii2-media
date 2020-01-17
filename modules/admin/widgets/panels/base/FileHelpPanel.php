<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\panels\base;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FileUpload;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\panels\HelpPanel;
use Yii;
use yii\web\JsExpression;

/**
 * Class FileHelpPanel
 * @package davidhirtz\yii2\media\modules\admin\widgets\base
 * @see \davidhirtz\yii2\media\modules\admin\widgets\panels\FileHelpPanel
 */
class FileHelpPanel extends HelpPanel
{
    /**
     * @var File
     */
    public $model;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->title === null) {
            $this->title = Yii::t('media', 'Operations');
        }

        if ($this->content === null) {
            $this->content = $this->renderButtonToolbar($this->getButtons());
        }

        parent::init();
    }

    /**
     * @return array
     */
    protected function getButtons(): array
    {
        return array_filter([
            $this->getDuplicateFileButton(),
            $this->getReplaceFileButton(),
        ]);
    }

    /**
     * @return string
     */
    protected function getDuplicateFileButton()
    {
        return Html::a(Html::iconText('copy', Yii::t('media', 'Duplicate')), ['clone', 'id' => $this->model->id], [
            'class' => 'btn btn-primary',
            'data-method' => 'post',
        ]);
    }

    /**
     * @return string
     */
    protected function getReplaceFileButton()
    {
        return Html::tag('div', Html::iconText('sync-alt', Yii::t('media', 'Replace file') . $this->getFileUploadWidget()), ['class' => 'btn btn-primary btn-upload']);
    }

    /**
     * @return string
     */
    protected function getFileUploadWidget()
    {
        return FileUpload::widget([
            'clientEvents' => [
                'fileuploaddone' => new JsExpression('function(){location.reload();}')
            ],
        ]);
    }
}