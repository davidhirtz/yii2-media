<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Traits;

use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\AssetPreviewField;
use Stringable;
use yii\db\ActiveRecord;

/**
 * `model` is deliberately not declared here, see monorepo issue #125: the using form declares it itself, and two
 * declarations of the same name drop that class's whole PHPDoc scope.
 */
trait AssetFieldsTrait
{
    protected function getPreview(): ?Stringable
    {
        return AssetPreviewField::make()
            ->asset($this->model);
    }
}
