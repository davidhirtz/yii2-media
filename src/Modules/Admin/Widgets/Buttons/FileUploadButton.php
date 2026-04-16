<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Media\Helpers\Html;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\ModuleTrait;
use Override;
use Yii;

class FileUploadButton extends \Hirtz\Skeleton\Widgets\Buttons\FileUploadButton
{
    use ModuleTrait;

    #[Override]
    protected function configure(): void
    {
        $this->label ??= Yii::t('media', 'Upload File');
        $this->icon ??= 'upload';

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
