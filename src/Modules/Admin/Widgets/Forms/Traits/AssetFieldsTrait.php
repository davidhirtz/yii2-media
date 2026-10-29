<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Traits;

use Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\AssetPreviewField;
use Stringable;

/**
 * `model` is not declared here (#125), since the using form declares it and two declarations drop its whole PHPDoc
 * scope.
 */
trait AssetFieldsTrait
{
    protected function getPreview(): ?Stringable
    {
        return AssetPreviewField::make()
            ->asset($this->model);
    }
}
