<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Media\Helpers\Html;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\I18n\Lang;
use Override;

class FileUploadButton extends \Hirtz\Skeleton\Widgets\Buttons\FileUploadButton
{
    use ModuleTrait;

    #[Override]
    protected function configure(): void
    {
        $this->label ??= Lang::t('media', 'FILE_UPLOAD_UPLOAD_FILE');
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
