<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Models\Asset;
use Hirtz\Skeleton\Widgets\Grids\Columns\LinkColumn;
use Stringable;

/**
 * @template T of Asset
 * @extends LinkColumn<T>
 */
class AssetThumbnailColumn extends LinkColumn
{
    public function __construct(array $config = [])
    {
        $this->headerAttributes = ['class' => 'grid-col-thumbnail'];
        $this->content ??= $this->getThumbnail(...);

        parent::__construct($config);
    }

    /**
     * @param T $asset
     */
    protected function getThumbnail(Asset $asset): string|Stringable
    {
        return Thumbnail::make()->file($asset->file);
    }
}
