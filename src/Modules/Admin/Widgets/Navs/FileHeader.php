<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileButtonsTrait;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FileGridView;
use Hirtz\Skeleton\Widgets\Navs\ActionDropdown;
use Hirtz\Skeleton\Widgets\Navs\ModelHeader;
use Hirtz\Skeleton\Widgets\Traits\ProviderTrait;
use Override;
use Stringable;
use Yii;

/**
 * @property FileActiveDataProvider|null $provider
 *
 * @extends ModelHeader<File|null>
 */
class FileHeader extends ModelHeader
{
    use FileButtonsTrait;

    /**
     * @use ProviderTrait<FileActiveDataProvider|null>
     */
    use ProviderTrait;

    #[Override]
    protected function configure(): void
    {
        if ($this->model) {
            $this->title ??= $this->model->getOldAttribute('name') ?? Yii::t('media', 'COMMON_FILE');
        }

        if ($this->provider) {
            $this->title ??= Yii::t('media', 'COMMON_FILES');
            $this->url ??= ['/admin/media/file/index'];
            $this->subtitle ??= $this->getPaginationSubtitle($this->provider);

            $this->addContent($this->getFileActionDropdown());
        }

        parent::configure();
    }

    protected function getFileActionDropdown(): ?Stringable
    {
        return ActionDropdown::make()
            ->addItem($this->getFileUploadButton(), $this->getFileImportButton());
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function getFileUploadRoute(): array
    {
        return ['/admin/media/file/create', 'folder' => $this->provider->folder?->id];
    }

    protected function getFileUploadTarget(): string
    {
        return '#' . FileGridView::ID;
    }
}
