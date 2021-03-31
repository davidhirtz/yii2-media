<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\panels\base;

use davidhirtz\yii2\media\assets\AdminAsset;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\FileLinkButtonTrait;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FileUpload;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\panels\HelpPanel;
use Yii;
use yii\helpers\Url;
use yii\web\JsExpression;

/**
 * Class FileHelpPanel
 * @package davidhirtz\yii2\media\modules\admin\widgets\base
 * @see \davidhirtz\yii2\media\modules\admin\widgets\panels\FileHelpPanel
 */
class FileHelpPanel extends HelpPanel
{
    use FileLinkButtonTrait;

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
            $this->title = Yii::t('skeleton', 'Operations');
        }

        if ($this->content === null) {
            $this->content = $this->renderButtonToolbar($this->getButtons());
        }

        if (Yii::$app->getUser()->can('fileCreate', ['folder' => $this->model->folder])) {
            AdminAsset::register($view = $this->getView());
            $view->registerJs('Skeleton.mediaFileImport();');
        }

        parent::init();
    }

    /**
     * @return array
     */
    protected function getButtons(): array
    {
        $buttons = [];

        if (Yii::$app->getUser()->can('fileCreate', ['folder' => $this->model->folder])) {
            $buttons[] = $this->getDuplicateFileButton();
            $buttons[] = $this->getUploadFileButton();
            $buttons[] = $this->getImportFileButton();
        }

        $buttons[] = $this->getFileLinkButton();
        return $buttons;
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
    protected function getUploadFileButton()
    {
        return Html::tag('div', Html::iconText('upload', Yii::t('media', 'Replace file') . $this->getFileUploadWidget()), [
            'class' => 'btn btn-primary btn-upload',
        ]);
    }

    /**
     * @return string
     */
    protected function getImportFileButton()
    {
        return Html::tag('div', Html::iconText('cloud-upload-alt', Yii::t('media', 'Replace file')), [
            'class' => 'btn btn-primary btn-submit btn-import',
            'data' => [
                'title' => Yii::t('media', 'Import file from URL'),
                'url' => Url::current(),
                'placeholder' => Yii::t('media', 'Link'),
                'confirm' => Yii::t('media', 'Import'),
            ],
        ]);
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