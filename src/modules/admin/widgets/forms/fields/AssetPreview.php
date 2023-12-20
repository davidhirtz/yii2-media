<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\fields;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use yii\base\Widget;
use yii\helpers\Html;

class AssetPreview extends Widget
{
    /**
     * @var (ActiveRecord&AssetInterface)|null
     */
    public ?AssetInterface $asset = null;

    public function run()
    {
        $file = $this->asset->file;

        if ($file->hasPreview()) {
            $image = Html::img($file->getUrl(), [
                'id' => 'image',
                'class' => 'img-transparent',
            ]);

            if ($width = $file->width) {
                $image = Html::tag('div', $image, [
                    'style' => "max-width:{$width}px",
                ]);
            }

            return $image;
        }

        return '';
    }
}
