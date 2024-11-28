<?php

namespace davidhirtz\yii2\media\models\interfaces;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\models\interfaces\TypeAttributeInterface;
use yii\db\ActiveRecordInterface;

/**
 * @property int $id
 * @property int $file_id
 *
 * @property-read AssetParentInterface $parent {@see static::getParent}
 * @property-read File $file {@see static::getFile}
 */
interface AssetInterface extends ActiveRecordInterface, FileRelationInterface, TypeAttributeInterface
{
    public const TYPE_VIEWPORT_MOBILE = 2;
    public const TYPE_VIEWPORT_DESKTOP = 3;
    public const TYPE_META_IMAGE = 6;

    public function getParent(): AssetParentInterface;

    public function getAltText(): string;

    public function getSrcset(array|string|null $transformations = null, ?string $extension = null): array;

    public function getSizes(): ?string;

    public function getTransformationNames(): array;
}
