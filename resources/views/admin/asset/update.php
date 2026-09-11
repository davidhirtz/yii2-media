<?php

declare(strict_types=1);

/**
 * @see AbstractAssetController::actionUpdate()
 *
 * @var View $this
 * @var Asset $asset
 */

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Modules\Admin\Controllers\AbstractAssetController;
use Hirtz\Media\Modules\Admin\Widgets\Forms\AssetActiveForm;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetActionDropdown;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetModelHeader;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Forms\FormContainer;

echo AssetModelHeader::make()
    ->model($asset->model)
    ->content(AssetActionDropdown::make()
        ->model($asset));

echo FormContainer::make()
    ->title($asset->getTrailModelName())
    ->form(AssetActiveForm::make()
        ->model($asset));
