<?php

declare(strict_types=1);

namespace data\widgets\grids;

use davidhirtz\yii2\media\modules\admin\widgets\grids\traits\AssetColumnsTrait;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\GridView;

class TestAssetGridView extends GridView
{
    use AssetColumnsTrait;
}
