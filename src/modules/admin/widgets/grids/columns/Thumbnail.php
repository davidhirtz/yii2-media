<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\columns;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\Widget;

class Thumbnail extends Widget
{
    public ?File $file = null;

    public function run(): string
    {
        return $this->renderThumbnailContent();
    }

    protected function renderThumbnailContent(): string
    {
        if (!$this->file->hasPreview()) {
            return '';
        }

        $imageUrl = $this->file->getTransformationUrl('admin') ?: $this->file->getUrl();

        return Html::tag('div', '', [
            'style' => "background-image:url($imageUrl);",
            'class' => 'thumb',
        ]);
    }
}
