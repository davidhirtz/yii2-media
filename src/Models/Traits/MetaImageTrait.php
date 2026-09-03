<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Traits;

use Hirtz\Skeleton\I18n\Lang;
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
                'name' => Lang::t('media', 'META_IMAGE_META_IMAGE'),
                'hiddenFields' => $hiddenFields,
                'visible' => fn (self $asset) => !$asset->isSectionAsset(),
            ],
        ];
    }

    public static function getTypes(): array
    {
        return static::getViewportTypes() + static::getMetaImageTypeOptions();
    }
}
