<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Models\Transformation;
use Hirtz\Skeleton\Widgets\Grids\Columns\LinkColumn;
use Override;
use Stringable;
use yii\base\Model;

class FileThumbnailColumn extends LinkColumn
{
    protected string $format = 'html';
    public ?array $headerAttributes = ['style' => 'width:150px'];

    #[Override]
    protected function getValue(array|Model $model, string|int $key, int $index): string|Stringable
    {
        if ($model instanceof Transformation || $model instanceof AssetInterface) {
            $model = $model->file;
        }

        return $model instanceof File
            ? Thumbnail::make()->file($model)
            : '';
    }
}
