<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\fields;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use yii\base\Widget;

/**
 * Renders a preview of an asset.
 * This widget can be used to hook into the asset rendering process.
 */
class AssetPreview extends Widget
{
    public ?AssetInterface $asset = null;

    public function run(): string
    {
        return FilePreview::widget(['file' => $this->asset->file]);
    }
}
