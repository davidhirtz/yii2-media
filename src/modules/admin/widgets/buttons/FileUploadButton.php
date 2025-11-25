<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\buttons;

use davidhirtz\yii2\media\helpers\Html;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\html\Icon;
use Yii;

class FileUploadButton extends \davidhirtz\yii2\skeleton\widgets\buttons\FileUploadButton
{
    use ModuleTrait;

    #[\Override]
    protected function configure(): void
    {
        $this->label ??= Yii::t('media', 'Upload File');
        $this->icon ??= Icon::make()->name('upload');

        $this->inputAttributes['accept'] ??= $this->getAcceptMimeTypesFromModule();
        $this->inputAttributes['name'] ??= Html::getInputName(File::instance(), 'upload');

        parent::configure();
    }

    protected function getAcceptMimeTypesFromModule(): string
    {
        $extensions = array_map(fn (string $value): string => ".$value", static::getModule()->allowedExtensions);
        return implode(',', $extensions);
    }
}
