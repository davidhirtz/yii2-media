<?php

namespace davidhirtz\yii2\media\modules\admin\widgets;

use davidhirtz\yii2\media\modules\admin\widgets\forms\FileUpload;
use davidhirtz\yii2\skeleton\helpers\Html;
use Yii;
use yii\helpers\Url;

/**
 * Trait UploadTrait
 * @package davidhirtz\yii2\media\modules\admin\widgets
 */
trait UploadTrait
{
    /**
     * @return string
     */
    protected function getUploadFileButton()
    {
        return Html::tag('div', Html::iconText('upload', Yii::t('media', 'Upload Files') . $this->getFileUploadWidget()), [
            'class' => 'btn btn-primary btn-submit btn-upload',
        ]);
    }

    /**
     * @return string
     */
    protected function getImportFileButton()
    {
        return Html::tag('div', Html::iconText('cloud-upload-alt', Yii::t('media', 'Import')), [
            'class' => 'btn btn-primary btn-submit btn-import',
            'data' => [
                'title' => Yii::t('media', 'Import file from URL'),
                'url' => Url::toRoute($this->getCreateRoute()),
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
            'url' => $this->getCreateRoute(),
        ]);
    }
}