<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Traits;

use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\AssetPreviewField;
use Stringable;
use yii\db\ActiveRecord;

/**
 * @property ActiveRecord&AssetInterface<AssetModelInterface> $model
 */
trait AssetFieldsTrait
{
    protected function getPreview(): ?Stringable
    {
        return AssetPreviewField::make()
            ->asset($this->model);
    }
}
