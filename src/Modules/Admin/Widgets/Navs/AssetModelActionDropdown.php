<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileButtonsTrait;
use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Navs\ActionDropdown;
use Hirtz\Skeleton\Widgets\Traits\ProviderTrait;
use Override;
use Stringable;

class AssetModelActionDropdown extends ActionDropdown
{
    use FileButtonsTrait;

    /**
     * @use ProviderTrait<AssetArrayDataProvider>
     */
    use ProviderTrait;

    #[Override]
    protected function configure(): void
    {
        $this->addItem(
            $this->getFileUploadButton(),
            $this->getFileImportButton(),
            $this->getAssetLinkButton(),
        );

        parent::configure();
    }

    protected function getAssetLinkButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->text(Lang::t('media', 'COMMON_LINK_ASSETS'))
            ->icon('images')
            ->url($this->getFileUploadRoute());
    }

    #[Override]
    protected function getFileUploadRoute(): array
    {
        $model = $this->provider->model;

        return [
            $model->getAssetClass()::getAdminControllerRoute() . '/create',
            $model->getParamName() => $model->id,
        ];
    }
}
