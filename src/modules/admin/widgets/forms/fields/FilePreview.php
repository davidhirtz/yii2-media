<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\fields;

use davidhirtz\yii2\media\models\File;
use yii\base\Widget;
use yii\helpers\Html;

class FilePreview extends Widget
{
    public ?File $file = null;

    public function run(): string
    {
        return $this->file->hasPreview() ? $this->renderImageTag() : '';
    }

    protected function renderImageTag(): string
    {
        $tag = Html::img($this->file->getUrl(), [
            'id' => 'image',
            'class' => 'img-transparent',
        ]);

        if ($width = $this->file->width) {
            $tag = Html::tag('div', $tag, [
                'style' => "max-width:{$width}px",
            ]);
        }

        return $tag;
    }
}
