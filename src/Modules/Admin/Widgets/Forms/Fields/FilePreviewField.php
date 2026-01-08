<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Fields;

use Hirtz\Media\Helpers\AspectRatio;
use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Html\Traits\TagAttributesTrait;
use Hirtz\Skeleton\Widgets\Forms\FormRow;
use Hirtz\Skeleton\Widgets\Forms\Traits\RowAttributesTrait;
use Hirtz\Skeleton\Widgets\Widget;
use Override;
use Stringable;

class FilePreviewField extends Widget
{
    use TagAttributesTrait;
    use RowAttributesTrait;

    protected File $file;

    /**
     * Clone file to use old attributes for basename and sizes as they would only differ on an error in which case the
     * new attributes might not be accurate.
     */
    public function file(File $file): static
    {
        $this->file = clone $file;
        $this->file->setAttributes($this->file->getOldAttributes(), false);

        return $this;
    }

    #[Override]
    protected function configure(): void
    {
        $this->attributes['data-id'] ??= 'image';
        parent::configure();
    }

    #[Override]
    protected function renderContent(): string|Stringable
    {
        return $this->file->hasPreview()
            ? FormRow::make()
                ->attributes($this->rowAttributes)
                ->content($this->getContent())
            : '';
    }

    protected function getContent(): string|Stringable
    {
        $image = Img::make()
            ->src($this->file->getUrl())
            ->attributes($this->attributes)
            ->addClass('img-transparent');

        return Div::make()
            ->content($image)
            ->addStyle([
                'position' => 'relative',
                'aspect-ratio' => new AspectRatio($this->file),
                'max-width' => $this->file->width ? "min(100%,{$this->file->width}px)" : null,
                'max-height' => '70svh',
            ]);
    }
}
