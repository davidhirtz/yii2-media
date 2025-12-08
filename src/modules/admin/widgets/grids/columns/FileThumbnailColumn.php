<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\grids\columns;

use Hirtz\Media\models\File;
use Hirtz\Media\models\interfaces\AssetParentInterface;
use Hirtz\Media\models\Transformation;
use Hirtz\Skeleton\widgets\grids\columns\LinkColumn;
use Override;
use Stringable;
use yii\base\Model;

class FileThumbnailColumn extends LinkColumn
{
    public ?array $headerAttributes = ['style' => 'width:150px'];

    #[Override]
    protected function getBodyContent(array|Model $model, string|int $key, int $index): string|Stringable
    {
        if ($model instanceof Transformation || $model instanceof AssetParentInterface) {
            $model = $model->file;
        }

        return $model instanceof File
            ? Thumbnail::make()->file($model)
            : '';
    }
}
