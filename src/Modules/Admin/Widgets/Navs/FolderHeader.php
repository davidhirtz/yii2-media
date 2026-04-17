<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FolderCreateButton;
use Hirtz\Skeleton\Widgets\Navs\Header;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Hirtz\Skeleton\Widgets\Traits\ProviderTrait;
use Override;
use Stringable;
use Yii;

/**
 * @property Folder|null $model
 */
class FolderHeader extends Header
{
    use ModelTrait;
    use ProviderTrait;

    #[Override]
    protected function configure(): void
    {
        if ($this->model) {
            $this->title ??= $this->model->getOldAttribute('name') ?? Yii::t('media', 'Folder');
            $this->addContent($this->getFolderActionDropdown());
        }

        if ($this->provider) {
            $this->title ??= Yii::t('media', 'Folders');
            $this->url ??= ['/admin/media/folder/index'];
            $this->addContent($this->getFolderCreateButton());
        }

        if (!$this->provider) {
            $this->breadcrumbs ??= [Yii::t('media', 'Folders') => ['/admin/media/folder/index']];
        }

        parent::configure();
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
