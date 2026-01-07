<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Fields;

use Hirtz\Media\Models\Interfaces\AssetInterface;

class AssetPreviewField extends FilePreviewField
{
    protected AssetInterface $asset;

    public function asset(AssetInterface $asset): static
    {
        $this->asset = $asset;
        return $this->file($asset->file);
    }
}
