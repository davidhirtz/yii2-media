<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileDeleteButton;
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

    /**
     * Without it there is no duplicate button.
     *
     * @var array<int|string, mixed>|null
     */
    protected ?array $duplicateRoute = null;

    /**
     * @param array<int|string, mixed>|null $route
     */
    public function duplicateRoute(?array $route): static
    {
        $this->duplicateRoute = $route;
        return $this;
    }

    #[Override]
    protected function configure(): void
    {
        $this->addItem(
            $this->getUpdateFileButton(),
            $this->getReplaceFileButton(),
            $this->getDuplicateButton(),
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
                ->text(Yii::t('media', 'FILE_UPDATE_BUTTON'))
                ->url(['/admin/media/file/update', 'id' => $this->model->file_id])
                ->target('_blank')
            : null;
    }

    protected function getReplaceFileButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->icon('exchange-alt')
            ->text(Yii::t('media', 'ASSET_REPLACE_BUTTON'))
            ->url([
                ...$this->model::getAdminCreateRoute($this->model->model),
                'asset' => $this->model->id,
            ])
            ->visible($this->canManageAsset() && $this->model->model->allowsAssets());
    }

    protected function getDuplicateButton(): ?Stringable
    {
        return $this->duplicateRoute !== null
            ? Button::make()
                ->primary()
                ->icon('paste')
                ->text(Yii::t('media', 'ASSET_DUPLICATE_BUTTON'))
                ->url($this->duplicateRoute)
                ->visible($this->canManageAsset())
            : null;
    }

    protected function getAssetDeleteButton(): ?Stringable
    {
        return DeleteButton::make()
            ->label(Yii::t('media', 'ASSET_REMOVE_BUTTON'))
            ->title(Yii::t('media', 'ASSET_REMOVE_CONFIRM'))
            ->message(Yii::t('media', 'ASSET_REMOVE_TEXT'))
            ->url(['delete', 'id' => $this->model->id])
            ->visible($this->canManageAsset())
            ->model($this->model);
    }

    protected function getFileDeleteButton(): ?Stringable
    {
        return FileDeleteButton::make()
            ->url([
                '/admin/media/file/delete',
                'id' => $this->model->file_id,
                // the asset page goes with the file, so the list of the model's assets is where to land
                'returnUrl' => Url::to($this->model->getAdminModelRoute()),
            ])
            ->model($this->model->file);
    }

    protected function canManageAsset(): bool
    {
        return $this->webuser->can($this->model->getPermissionName());
    }
}
