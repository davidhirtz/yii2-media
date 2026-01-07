<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Interfaces\FileRelationInterface;
use Hirtz\Media\Traits\FilePropertyTrait;
use Hirtz\Skeleton\Widgets\Widget;
use Stringable;

class FileRelationGridContainer extends Widget
{
    use FilePropertyTrait;

    protected function renderContent(): string|Stringable
    {
        $content = '';

        foreach ($this->file->getActiveRelatedModels() as $relation) {
            /** @var class-string<FileRelationInterface> $relation */
            $content .= $relation::instance()->getFileRelationGridContainerClass()::make()
                ->file($this->file);
        }

        return $content;
    }
}
