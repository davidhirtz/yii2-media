<?php

namespace davidhirtz\yii2\media\models\traits;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\queries\FileQuery;
use Yii;

/**
 * AssetTrait implements the common methods and properties for the Assets.
 */
trait AssetTrait
{
    /**
     * @var bool whether the related file should also be deleted on delete if the current record was it's only linked
     * asset. Defaults to `false`.
     */
    public $deleteFileOnDelete = false;

    /**
     * @var string the name of the autoplay link attribute, defaults to "link".
     */
    public $autoplayLinkAttributeName = 'link';

    /**
     * Replaces the default YouTube and Vimeo link with an embed links.
     */
    public function validateAutoplayLink()
    {
        foreach ($this->getI18nAttributesNames($this->autoplayLinkAttributeName) as $attributeName) {
            if ($attribute = $this->getAttribute($attributeName)) {
                if (preg_match('~^https://vimeo.com/(\d+)~', $attribute, $matches)) {
                    $this->setAttribute($attributeName, "https://player.vimeo.com/video/$matches[1]");
                } else {
                    $this->setAttribute($attributeName, str_replace('/watch?v=', '/embed/', $attribute));
                }
            }
        }
    }

    /**
     * @return false|int
     */
    public function updateOrDeleteFileByAssetCount()
    {
        if (!$this->file->isDeleted()) {
            $this->file->recalculateAssetCountByAsset($this);

            if ($this->deleteFileOnDelete && !$this->file->getAssetCount()) {
                return $this->file->delete();
            }
            return $this->file->update();
        }

        return false;
    }

    /**
     * @return FileQuery
     */
    public function getFile(): FileQuery
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->hasOne(File::class, ['id' => 'file_id']);
    }

    /**
     * @param File $file
     */
    public function populateFileRelation($file)
    {
        $this->populateRelation('file', $file);
        $this->file_id = $file->id;
    }

    /**
     * @return string
     */
    public function getAltText()
    {
        return $this->getI18nAttribute('alt_text') ?: $this->file->getI18nAttribute('alt_text') ?: '';
    }

    /**
     * @param null $language
     * @return string
     */
    public function getAutoplayLink($language = null): string
    {
        if ($link = ($this->getI18nAttribute($this->autoplayLinkAttributeName, $language) ?: '')) {
            $link = $link . (str_contains($link, '?') ? '&' : '?') . 'autoplay=1';

            if (strpos($link, 'youtube')) {
                $link .= '&disablekb=1&modestbranding=1&rel=0';
            }

            if (strpos($link, 'vimeo')) {
                $link .= '&dnt=1';
            }
        }

        return $link;
    }

    /**
     * @return string
     */
    public function getTrailModelName()
    {
        if ($this->id) {
            return Yii::t('skeleton', '{model} #{id}', [
                'model' => $this->getTrailModelType(),
                'id' => $this->id,
            ]);
        }

        return $this->getTrailModelType();
    }

    /**
     * @param array|string|null $transformations
     * @param string|null $extension
     * @return array|string
     */
    public function getSrcset($transformations = null, $extension = null)
    {
        return $this->file->getSrcset($transformations ?? $this->getTransformationNames(), $extension);
    }

    /**
     * @return string|null containing the HTML sizes attribute content
     * @see https://html.spec.whatwg.org/multipage/images.html#sizes-attributes
     */
    public function getSrcsetSizes()
    {
        return null;
    }

    /**
     * @return array
     */
    public function getTransformationNames(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public static function getViewportTypes(): array
    {
        return [
            static::TYPE_DEFAULT => [
                'name' => Yii::t('media', 'All devices'),
            ],
            static::TYPE_VIEWPORT_MOBILE => [
                'name' => Yii::t('media', 'Mobile'),
            ],
            static::TYPE_VIEWPORT_DESKTOP => [
                'name' => Yii::t('media', 'Desktop'),
            ],
        ];
    }

    /**
     * @return string
     */
    public function formName(): string
    {
        return 'Asset';
    }
}
