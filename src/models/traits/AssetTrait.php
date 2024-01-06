<?php

namespace davidhirtz\yii2\media\models\traits;

use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\skeleton\models\traits\TypeAttributeTrait;
use Yii;
use yii\db\ActiveRecord;

/**
 * @property ActiveRecord&AssetParentInterface $parent
 */
trait AssetTrait
{
    use FileRelationTrait;
    use TypeAttributeTrait;

    /**
     * @var bool whether the related file should also be deleted on deleting if the current record was it's only linked
     * asset. Defaults to `false`.
     */
    public bool $deleteFileOnDelete = false;

    public function updateOrDeleteFileByAssetCount(): bool|int
    {
        if ($this->file->isDeleted()) {
            return false;
        }

        $this->file->recalculateAssetCountByAsset($this);

        if ($this->deleteFileOnDelete && !$this->file->getAssetCount()) {
            return $this->file->delete();
        }

        return $this->file->update();
    }

    public function getAltText(): string
    {
        $text = method_exists($this, 'getI18nAttribute')
            ? $this->getI18nAttribute('alt_text')
            : $this->getAttribute('alt_text');

        return ($text ?: $this->file->getI18nAttribute('alt_text')) ?: '';
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

    public function getTrailModelType(): string
    {
        return Yii::t('media', 'Asset');
    }

    public function getSrcset(array|string|null $transformations = null, ?string $extension = null): array
    {
        return $this->file->getSrcset($transformations ?? $this->getTransformationNames(), $extension);
    }

    /**
     * @see https://html.spec.whatwg.org/multipage/images.html#sizes-attributes
     */
    public function getSizes(): ?string
    {
        return $this->parent->getAssetSizes();
    }

    public function getTransformationNames(): array
    {
        return $this->parent->getAssetTransformationNames() ?: $this->file->getTransformationNames();
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
}
