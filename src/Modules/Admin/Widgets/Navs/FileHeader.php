<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Skeleton\Widgets\Navs\Header;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Hirtz\Skeleton\Widgets\Traits\ProviderTrait;
use Override;
use Stringable;
use Yii;

/**
 * @property File|null $model
 * @property FileActiveDataProvider|null $provider
 */
class FileHeader extends Header
{
    use ModelTrait;
    use ProviderTrait;

    #[Override]
    protected function configure(): void
    {
        if ($this->model) {
            $this->breadcrumbs ??= [Yii::t('media', 'Files') => ['/admin/media/file/index']];
            $this->title ??= $this->model->getOldAttribute('name') ?? Yii::t('media', 'File');
            $this->addContent($this->getFileActionDropdown());
        }

        if ($this->provider) {
            $this->title ??= Yii::t('media', 'Files');
            $this->url ??= ['/admin/media/file/index'];
            $this->subtitle ??= $this->getPaginationSubtitle($this->provider);
        }

        parent::configure();
    }

    protected function getFileActionDropdown(): ?Stringable
    {
        return FileActionDropdown::make()
            ->model($this->model);
    }
}
