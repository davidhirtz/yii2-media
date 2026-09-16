<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Traits;

use Hirtz\Media\Models\Asset;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\DeleteGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Yii;

trait AssetGridViewTrait
{
    protected function getDimensionsColumn(): ?Column
    {
        return DataColumn::make()
            ->property('dimensions')
            ->content($this->getDimensionsColumnContent(...));
    }

    protected function getDimensionsColumnContent(Asset $asset): string
    {
        return $asset->file->hasDimensions() ? $asset->file->getDimensions() : '-';
    }

    /**
     * Absolute routes: the grid is rendered by the asset controller of the subclass and by the file controller alike.
     */
    protected function getDeleteButton(Asset $asset): DeleteGridButton
    {
        return DeleteGridButton::make()
            ->model($asset)
            ->title(Yii::t('media', 'COMMON_REMOVE_TITLE'))
            ->url([$asset::getAdminControllerRoute() . '/delete', 'id' => $asset->id]);
    }
}
