<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\columns;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\skeleton\widgets\grids\columns\LinkColumn;
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
