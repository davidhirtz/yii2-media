<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Buttons\DeleteButton;
use Hirtz\Skeleton\Widgets\Buttons\DuplicateButton;
use Hirtz\Skeleton\Widgets\Navs\ActionDropdown;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Override;
use Stringable;
use Yii;

class AssetActionDropdown extends ActionDropdown
{
    /**
     * @use ModelTrait<Asset>
     */
    use ModelTrait;

    #[Override]
    protected function configure(): void
    {
        $this->addItem(
            $this->getUpdateFileButton(),
            $this->getDuplicateButton(),
            $this->getAssetDeleteButton(),
            $this->getFileDeleteButton(),
        );

        parent::configure();
    }

    protected function getUpdateFileButton(): ?Stringable
    {
        return $this->webuser->can(File::AUTH_FILE_CREATE)
            ? Button::make()
                ->primary()
                ->icon('image')
                ->text(Yii::t('media', 'COMMON_EDIT_FILE'))
                ->url(['/admin/media/file/update', 'id' => $this->model->file_id])
                ->target('_blank')
            : null;
    }

    protected function getDuplicateButton(): ?Stringable
    {
        return DuplicateButton::make()
            ->model($this->model);
    }

    protected function getAssetDeleteButton(): ?Stringable
    {
        return DeleteButton::make()
            ->label(Yii::t('media', 'ASSET_ACTION_DROPDOWN_DELETE'))
            ->message(Yii::t('media', 'ASSET_ACTION_DROPDOWN_DELETE_MESSAGE'))
            ->url(['delete', 'id' => $this->model->id])
            ->visible($this->canDeleteAsset())
            ->model($this->model);
    }

    protected function getFileDeleteButton(): ?Stringable
    {
        return DeleteButton::make()
            ->label(Yii::t('media', 'FILE_ACTION_DROPDOWN_DELETE_FILE'))
            ->message(Yii::t('media', 'ASSET_ACTION_DROPDOWN_DELETE_FILE_MESSAGE'))
            ->url(['/admin/media/file/delete', 'id' => $this->model->file_id])
            ->visible($this->webuser->can(File::AUTH_FILE_DELETE, ['file' => $this->model->file]))
            ->model($this->model->file);
    }

    protected function canDeleteAsset(): bool
    {
        $model = $this->model->model;

        return $this->webuser->can($this->model->getPermissionName('delete'), [
            'asset' => $this->model,
            $model->getParamName() => $model,
        ]);
    }
}
