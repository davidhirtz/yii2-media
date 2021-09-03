<?php

namespace davidhirtz\yii2\media\models\traits;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\queries\FileQuery;
use Yii;

/**
 * AssetTrait
 * @package davidhirtz\yii2\media\models\traits
 */
trait AssetTrait
{

    /**
     * @var bool whether the related file should also be deleted on delete if the current record was it's only linked
     * asset. Defaults to `false`.
     */
    public $deleteFileOnDelete = false;

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
        return $this->file->getSrcset($transformations, $extension);
    }

    /**
     * @param string|null $language
     * @return string
     */
    public function getAutoplayLink($language = null): string
    {
        return ($link = $this->getI18nAttribute('link', $language)) ? ($link . (strpos($link, '?') !== false ? '&' : '?') . 'autoplay=1') : '';
    }

    /**
     * @return array
     */
    public static function getViewportTypes(): array
    {
        return [
            static::TYPE_DEFAULT => [
                'name' => Yii::t('cms', 'All devices'),
            ],
            static::TYPE_VIEWPORT_MOBILE => [
                'name' => Yii::t('cms', 'Mobile'),
            ],
            static::TYPE_VIEWPORT_DESKTOP => [
                'name' => Yii::t('cms', 'Desktop'),
            ],
        ];
    }

    /**
     * @return string
     */
    public function getAltText()
    {
        return $this->getI18nAttribute('alt_text') ?: $this->file->getI18nAttribute('alt_text');
    }

    /**
     * @return string
     */
    public function formName(): string
    {
        return 'Asset';
    }
}
