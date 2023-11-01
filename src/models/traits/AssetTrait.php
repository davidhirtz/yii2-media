<?php

namespace davidhirtz\yii2\media\models\traits;

use Yii;

/**
 * AssetTrait implements the common methods and properties for the Assets.
 */
trait AssetTrait
{
    use FileRelationTrait;

    /**
     * @var bool whether the related file should also be deleted on deleting if the current record was it's only linked
     * asset. Defaults to `false`.
     */
    public bool $deleteFileOnDelete = false;

    /**
     * @var string the name of the autoplay link attribute, defaults to "link".
     */
    public string $autoplayLinkAttributeName = 'link';

    /**
     * Replaces the default YouTube and Vimeo link with an embed links.
     */
    public function validateAutoplayLink(): void
    {
        foreach ($this->getI18nAttributesNames($this->autoplayLinkAttributeName) as $attributeName) {
            if ($attribute = $this->getAttribute($attributeName)) {
                if (preg_match('~^https://vimeo.com/(\d+)~', (string) $attribute, $matches)) {
                    $this->setAttribute($attributeName, "https://player.vimeo.com/video/$matches[1]");
                } else {
                    $this->setAttribute($attributeName, str_replace('/watch?v=', '/embed/', (string) $attribute));
                }
            }
        }
    }

    public function updateOrDeleteFileByAssetCount(): bool|int
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

    public function getAltText(): string
    {
        return ($this->getI18nAttribute('alt_text') ?: $this->file->getI18nAttribute('alt_text')) ?: '';
    }

    public function getAutoplayLink(?string $language = null): string
    {
        if ($link = ($this->getI18nAttribute($this->autoplayLinkAttributeName, $language) ?: '')) {
            $link = $link . (str_contains((string) $link, '?') ? '&' : '?') . 'autoplay=1';

            if (strpos($link, 'youtube')) {
                $link .= '&disablekb=1&modestbranding=1&rel=0';
            }

            if (strpos($link, 'vimeo')) {
                $link .= '&dnt=1';
            }
        }

        return $link;
    }

    public function getTrailModelName(): string
    {
        if ($this->id) {
            return Yii::t('skeleton', '{model} #{id}', [
                'model' => $this->getTrailModelType(),
                'id' => $this->id,
            ]);
        }

        return $this->getTrailModelType();
    }

    public function getSrcset(array|string|null $transformations = null, ?string $extension = null): array|string
    {
        return $this->file->getSrcset($transformations ?? $this->getTransformationNames(), $extension);
    }

    /**
     * @return string|null containing the HTML sizes attribute content
     * @see https://html.spec.whatwg.org/multipage/images.html#sizes-attributes
     */
    public function getSrcsetSizes(): ?string
    {
        return null;
    }

    public function getTransformationNames(): array
    {
        return [];
    }

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

    public function formName(): string
    {
        return 'Asset';
    }
}
