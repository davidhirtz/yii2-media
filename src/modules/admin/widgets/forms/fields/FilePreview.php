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
        return $this->renderField();
    }

    protected function renderField(): string
    {
        if ($this->file->hasPreview()) {
            $image = Html::img($this->file->getUrl(), [
                'id' => 'image',
                'class' => 'img-transparent',
            ]);

            if ($width = $this->file->width) {
                $image = Html::tag('div', $image, [
                    'style' => "max-width:{$width}px",
                ]);
            }

            return $image;
        }

        return '';
    }
}
