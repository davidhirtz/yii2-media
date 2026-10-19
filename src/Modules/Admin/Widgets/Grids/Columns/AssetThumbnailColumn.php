<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Models\Asset;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Widgets\Grids\Columns\LinkColumn;
use Hirtz\Skeleton\Widgets\Icon;
use Stringable;

/**
 * @template T of Asset
 * @extends LinkColumn<T>
 */
class AssetThumbnailColumn extends LinkColumn
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->headerAttributes = ['class' => 'grid-col-thumbnail'];
        $this->format ??= 'raw';

        // The value: `LinkColumn` wraps that in its link, while assigning the content leaves `getLink()` unreached.
        $this->value ??= $this->getThumbnail(...);

        parent::__construct($config);
    }

    /**
     * @param T $asset
     */
    protected function getThumbnail(Asset $asset): string|Stringable
    {
        $thumbnail = (string)Thumbnail::make()->file($asset->file);
        $badges = $this->getThumbnailBadges($asset);

        if ($thumbnail === '' || !$badges) {
            return $thumbnail;
        }

        return Div::make()
            ->class('thumbnail')
            ->content($thumbnail, Div::make()
                ->class('thumbnail-badges')
                ->content(...$badges));
    }

    /**
     * @param T $asset
     * @return list<Stringable>
     */
    protected function getThumbnailBadges(Asset $asset): array
    {
        $icon = $asset->getTypeIcon();

        if (!$icon) {
            return [];
        }

        return [
            Div::make()
                ->class('badge')
                ->content(Icon::make()
                    ->name($icon)
                    ->tooltip($asset->getTypeName())),
        ];
    }
}
