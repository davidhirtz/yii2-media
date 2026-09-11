<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Traits;

use Hirtz\Skeleton\Widgets\Forms\Fields\SelectField;
use Hirtz\Skeleton\Widgets\Forms\Fields\TypeSelectField;
use Stringable;

trait AssetFormFieldsTrait
{
    protected function getStatusField(): ?Stringable
    {
        return SelectField::make()
            ->property('status');
    }

    protected function getTypeField(): ?Stringable
    {
        return TypeSelectField::make()
            ->property('type');
    }
}
