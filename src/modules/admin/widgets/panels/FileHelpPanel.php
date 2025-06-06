<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\panels;

use davidhirtz\yii2\media\assets\AdminAsset;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FileUpload;
use davidhirtz\yii2\media\modules\admin\widgets\panels\traits\DuplicateButtonTrait;
use davidhirtz\yii2\media\modules\admin\widgets\panels\traits\FileLinkButtonTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\panels\HelpPanel;
use Yii;
use yii\helpers\Url;
use yii\web\JsExpression;

class FileHelpPanel extends HelpPanel
{
    use DuplicateButtonTrait;
    use FileLinkButtonTrait;

    public ?File $model = null;

    public function init(): void
    {
        $this->content ??= $this->renderButtonToolbar($this->getButtons());

        if (Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $this->model->folder])) {
            AdminAsset::register($view = $this->getView());
            $view->registerJs('Skeleton.mediaFileImport();');
        }

        parent::init();
    }

    protected function getButtons(): array
    {
        $buttons = [];

        if (Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $this->model->folder])) {
            $buttons[] = $this->getDuplicateButton();
            $buttons[] = $this->getUploadFileButton();
            $buttons[] = $this->getImportFileButton();
        }

        $buttons[] = $this->getFileLinkButton();

        return $buttons;
    }

    protected function getUploadFileButton(): string
    {
        $content = Yii::t('media', 'Replace file');
        $content = Html::iconText('upload', $content . $this->getFileUploadWidget());

        return Html::tag('div', $content, [
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
