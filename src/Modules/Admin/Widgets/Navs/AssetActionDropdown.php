<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Buttons\DeleteButton;
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
            $this->getReplaceFileButton(),
            $this->getAssetDeleteButton(),
            $this->getFileDeleteButton(),
        );

        parent::configure();
    }

    protected function getUpdateFileButton(): ?Stringable
    {
        return $this->webuser->can(File::AUTH_FILE)
            ? Button::make()
                ->primary()
                ->icon('image')
                ->text(Yii::t('media', 'COMMON_EDIT_FILE'))
                ->url(['/admin/media/file/update', 'id' => $this->model->file_id])
                ->target('_blank')
            : null;
    }

    protected function getReplaceFileButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->icon('exchange-alt')
            ->text(Yii::t('media', 'ASSET_ACTION_DROPDOWN_REPLACE_FILE'))
            ->url([
                ...$this->model::getAdminCreateRoute($this->model->model),
                'asset' => $this->model->id,
            ])
            ->visible($this->canManageAsset());
    }

    protected function getAssetDeleteButton(): ?Stringable
    {
        return DeleteButton::make()
            ->label(Yii::t('media', 'ASSET_BUTTON_REMOVE'))
            ->title(Yii::t('media', 'ASSET_CONFIRM_REMOVE'))
            ->message(Yii::t('media', 'ASSET_ACTION_DROPDOWN_DELETE_MESSAGE'))
            ->url(['delete', 'id' => $this->model->id])
            ->visible($this->canManageAsset())
            ->model($this->model);
    }

    protected function getFileDeleteButton(): ?Stringable
    {
        return DeleteButton::make()
            ->label(Yii::t('media', 'FILE_BUTTON_DELETE'))
            ->title(Yii::t('media', 'FILE_CONFIRM_DELETE'))
            ->message(Yii::t('media', 'ASSET_ACTION_DROPDOWN_DELETE_FILE_MESSAGE'))
            ->url([
                '/admin/media/file/delete',
                'id' => $this->model->file_id,
                // the asset page goes with the file, so the list of the model's assets is where to land
                'returnUrl' => Url::to($this->model::getAdminIndexRoute($this->model->model)),
            ])
            ->visible($this->webuser->can(File::AUTH_FILE))
            ->model($this->model->file);
    }

    protected function canManageAsset(): bool
    {
        return $this->webuser->can($this->model->getPermissionName());
    }
}
