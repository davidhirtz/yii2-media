<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\panels;

use davidhirtz\yii2\media\assets\AdminAsset;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\controllers\FileController;
use davidhirtz\yii2\media\modules\admin\widgets\panels\traits\FileLinkButtonTrait;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FileUpload;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\panels\HelpPanel;
use Yii;
use yii\helpers\Url;
use yii\web\JsExpression;

class FileHelpPanel extends HelpPanel
{
    use FileLinkButtonTrait;

    public ?File $model = null;

    public function init(): void
    {
        $this->title ??= Yii::t('skeleton', 'Operations');
        $this->content ??= $this->renderButtonToolbar($this->getButtons());

        if (Yii::$app->getUser()->can('fileCreate', ['folder' => $this->model->folder])) {
            AdminAsset::register($view = $this->getView());
            $view->registerJs('Skeleton.mediaFileImport();');
        }

        parent::init();
    }

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
     * @see FileController::actionClone()
     */
    protected function getDuplicateFileButton(): string
    {
        return Html::a(Html::iconText('copy', Yii::t('media', 'Duplicate')), ['clone', 'id' => $this->model->id], [
            'class' => 'btn btn-primary',
            'data-method' => 'post',
        ]);
    }

    protected function getUploadFileButton(): string
    {
        return Html::tag('div', Html::iconText('upload', Yii::t('media', 'Replace file') . $this->getFileUploadWidget()), [
            'class' => 'btn btn-primary btn-upload',
        ]);
    }

    protected function getImportFileButton(): string
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

    protected function getFileUploadWidget(): string
    {
        return FileUpload::widget([
            'clientEvents' => [
                'fileuploaddone' => new JsExpression('function(){location.reload();}')
            ],
        ]);
    }
}