<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Widgets\Grids\Columns\BadgeColumn;
use Override;
use Stringable;
use yii\base\Model;

class AssetCountColumn extends BadgeColumn
{
    public function __construct()
    {
        $this->property ??= 'asset_count';
        $this->url ??= fn (AssetModelInterface $model) => $model->getAssetClass()::getAdminIndexRoute($model);

        parent::__construct();
    }

    #[Override]
    public function isVisible(): bool
    {
        if (parent::isVisible()) {
            foreach ($this->grid->provider->getModels() as $model) {
                if ($model instanceof AssetModelInterface && $model->hasAssetsEnabled()) {
                    return true;
                }
            }
        }

        return false;
    }

    #[Override]
    protected function getBody(array|Model $model, string|int $key, int $index): string|Stringable
    {
        return $model instanceof AssetModelInterface && $model->hasAssetsEnabled()
            ? parent::getBody($model, $key, $index)
            : '';
    }
}
