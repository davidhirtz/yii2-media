<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Models\Transformation;
use Hirtz\Skeleton\Widgets\Grids\Columns\LinkColumn;
use Stringable;
use yii\base\Model;

class FileThumbnailColumn extends LinkColumn
{
    public function __construct(array $config = [])
    {
        $this->headerAttributes = ['class' => 'grid-col-thumbnail'];
        $this->format ??= 'raw';

        $this->value ??= $this->getThumbnail(...);

        parent::__construct($config);
    }

    protected function getThumbnail(array|Model $model): string|Stringable
    {
        if ($model instanceof Transformation || $model instanceof AssetInterface) {
            $model = $model->file;
        }

        return $model instanceof File
            ? Thumbnail::make()->file($model)
            : '';
    }
}
