<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\fields;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use Stringable;
use Yii;

class AssetPreview implements Stringable
{
    public function __construct(protected AssetInterface $asset)
    {
    }

    public function __toString(): string
    {
        return (string)Yii::createObject(FilePreview::class, [$this->asset->file]);
    }
}
