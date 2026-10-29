<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FolderCreateButton;
use Hirtz\Skeleton\Widgets\Navs\ModelHeader;
use Hirtz\Skeleton\Widgets\Traits\ProviderTrait;
use yii\data\ActiveDataProvider;
use Override;
use Stringable;
use Yii;

/**
 * @extends ModelHeader<Folder|null>
 */
class FolderHeader extends ModelHeader
{
    /**
     * @use ProviderTrait<ActiveDataProvider|null>
     */
    use ProviderTrait;

    #[Override]
    protected function configure(): void
    {
        if ($this->model) {
            $this->title ??= $this->model->getOldAttribute('name') ?? Yii::t('media', 'FOLDER_SINGULAR');
            $this->addContent($this->getFolderActionDropdown());
        }

        if ($this->provider) {
            $this->title ??= Yii::t('media', 'FOLDER_PLURAL');
            $this->url ??= ['/admin/media/folder/index'];
            $this->addContent($this->getFolderCreateButton());
        }

        $this->addFileBreadcrumb();

        parent::configure();
    }

    protected function addFileBreadcrumb(): void
    {
        if (!$this->webuser->can(File::AUTH_FILE)) {
            return;
        }

        $this->addBreadcrumb(Yii::t('media', 'FILE_PLURAL'), ['/admin/media/file/index']);
    }

    protected function getFolderActionDropdown(): ?Stringable
    {
        return FolderActionDropdown::make()
            ->model($this->model);
    }

    protected function getFolderCreateButton(): ?Stringable
    {
        return FolderCreateButton::make();
    }
}
