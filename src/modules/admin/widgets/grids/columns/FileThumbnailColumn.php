<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\columns;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\columns\LinkDataColumn;
use Override;

class FileThumbnailColumn extends LinkDataColumn
{
    public $headerOptions = ['style' => 'width:150px'];

    #[Override]
    public function init(): void
    {
        if ($this->content === null) {
            $this->content = $this->renderThumbnail(...);
        }

        parent::init();
    }

    private function renderThumbnail(File $model): string
    {
        return Thumbnail::widget(['file' => $model]);
    }
}
