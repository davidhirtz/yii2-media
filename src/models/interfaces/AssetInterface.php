<?php

namespace davidhirtz\yii2\media\models\interfaces;

use davidhirtz\yii2\media\models\File;
use yii\db\ActiveQuery;
use yii\db\ActiveRecordInterface;

/**
 * @property int $file_id
 * @property-read File $file {@see static::getFile}
 */
interface AssetInterface extends ActiveRecordInterface
{
    public const TYPE_VIEWPORT_MOBILE = 2;
    public const TYPE_VIEWPORT_DESKTOP = 3;
    public const TYPE_META_IMAGE = 6;

    public function getFile(): ActiveQuery;

    public function getFileCountAttribute(): string;

    public function getParent(): AssetParentInterface;

    public function getParentGridView(): string;

    public function getParentName(): string;

    public function getAltText(): string;

    public function getSrcset(array|string|null $transformations = null, ?string $extension = null): array|string;

    public function getSizes(): ?string;

    public function getTransformationNames(): array;
}
