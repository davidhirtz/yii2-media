<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileButtonsTrait;
use Hirtz\Skeleton\Models\Breadcrumb;
use Hirtz\Skeleton\Widgets\Buttons\ButtonGroup;
use Hirtz\Skeleton\Widgets\Navs\Header;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Hirtz\Skeleton\Widgets\Traits\ProviderTrait;
use Override;
use Stringable;
use Yii;

/**
 * @property FileActiveDataProvider|null $provider
 */
class FileHeader extends Header
{
    use FileButtonsTrait;

    /**
     * @use ModelTrait<File|null>
     */
    use ModelTrait;

    /**
     * @use ProviderTrait<FileActiveDataProvider|null>
     */
    use ProviderTrait;

    #[Override]
    protected function configure(): void
    {
        if ($this->model) {
            $this->breadcrumbs ??= [
                new Breadcrumb(Lang::t('media', 'COMMON_FILES'), ['/admin/media/file/index']),
            ];

            $this->title ??= $this->model->getOldAttribute('name') ?? Lang::t('media', 'COMMON_FILE');
            $this->addContent($this->getFileActionDropdown());
        }

        if ($this->provider) {
            $this->title ??= Lang::t('media', 'COMMON_FILES');
            $this->url ??= ['/admin/media/file/index'];
            $this->subtitle ??= $this->getPaginationSubtitle($this->provider);
            $this->addContent($this->getFileButtonGroup());
        }

        parent::configure();
    }

    protected function getFileActionDropdown(): ?Stringable
    {
        return FileActionDropdown::make()
            ->model($this->model);
    }

    protected function getFileButtonGroup(): ?Stringable
    {
        return ButtonGroup::make()
            ->content($this->getFileUploadButton(), $this->getFileImportButton());
    }

    protected function getFileUploadRoute(): array
    {
        return ['/admin/media/file/upload', 'folder' => $this->provider->folder?->id];
    }
}
