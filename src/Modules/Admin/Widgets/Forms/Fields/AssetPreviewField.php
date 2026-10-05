<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Fields;

use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Skeleton\Html\Traits\TagAttributesTrait;
use Hirtz\Skeleton\Widgets\Forms\Traits\RowAttributesTrait;
use Hirtz\Skeleton\Widgets\Widget;
use Override;
use Stringable;

/**
 * Renders the file's preview through `FilePreviewField::make()`, so the class the container swaps in for it (a video
 * player) renders the asset's preview too.
 */
class AssetPreviewField extends Widget
{
    use TagAttributesTrait;
    use RowAttributesTrait;

    protected AssetInterface $asset;

    public function asset(AssetInterface $asset): static
    {
        $this->asset = $asset;
        return $this;
    }

    #[Override]
    protected function renderContent(): string|Stringable
    {
        return FilePreviewField::make()
            ->attributes($this->attributes)
            ->rowAttributes($this->rowAttributes)
            ->file($this->asset->file)
            ->render();
    }
}
