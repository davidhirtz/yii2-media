<?php

namespace davidhirtz\yii2\media\models\traits;

use Yii;

trait MetaImageTrait
{
    public static function getMetaImageTypeOptions(): array
    {
        $hiddenFields = array_diff(static::instance()->attributes(), [
            'status',
            'type',
        ]);

        return [
            static::TYPE_META_IMAGE => [
                'name' => Yii::t('media', 'Meta Image'),
                'hiddenFields' => $hiddenFields,
                'visible' => fn(self $asset) => !$asset->isSectionAsset(),
            ],
        ];
    }

    public static function getTypes(): array
    {
        return static::getViewportTypes() + static::getMetaImageTypeOptions();
    }
}