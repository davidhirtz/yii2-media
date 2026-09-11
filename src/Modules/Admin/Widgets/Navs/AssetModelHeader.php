<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Widgets\Navs\Header;
use Override;

/**
 * The header of the default asset pages: the record's own name, linking back to its admin page.
 */
class AssetModelHeader extends Header
{
    protected AssetModelInterface $model;

    public function model(AssetModelInterface $model): static
    {
        $this->model = $model;
        return $this;
    }

    #[Override]
    protected function configure(): void
    {
        $this->title ??= Asset::getModelName($this->model);
        $this->url ??= $this->model->getAssetClass()::getAdminIndexRoute($this->model);

        $this->addBreadcrumb(Lang::t('media', 'ASSET_MODEL_LABEL'), $this->url);

        parent::configure();
    }
}
