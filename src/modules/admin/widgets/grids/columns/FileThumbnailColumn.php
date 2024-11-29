<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\columns;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\columns\LinkDataColumn;

class FileThumbnailColumn extends LinkDataColumn
{
    public $headerOptions = ['style' => 'width:150px'];

    public function init(): void
    {
        if (!is_callable($this->content)) {
            $this->content = $this->renderThumbnail(...);
        }

        parent::init();
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    protected function renderThumbnail(File $model, int $key, int $index): string
    {
        return Thumbnail::widget(['file' => $model]);
    }
}
