<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Media\helpers\Html;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Html\Icon;
use Yii;

class FileUploadButton extends \Hirtz\Skeleton\Widgets\Buttons\FileUploadButton
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
