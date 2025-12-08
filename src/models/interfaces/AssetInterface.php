<?php

declare(strict_types=1);

namespace Hirtz\Media\models\interfaces;

use Hirtz\Media\models\File;
use Hirtz\Skeleton\db\ActiveRecord;
use Hirtz\Skeleton\models\interfaces\TypeAttributeInterface;
use yii\db\ActiveRecordInterface;

/**
 * @property int $id
 * @property int $file_id
 *
 * @property-read AssetParentInterface $parent {@see static::getParent}
 * @property-read File $file {@see static::getFile}
 *
 * @phpstan-require-extends ActiveRecord
 */
interface AssetInterface extends ActiveRecordInterface, FileRelationInterface, TypeAttributeInterface
{
    public const int TYPE_VIEWPORT_MOBILE = 2;
    public const int TYPE_VIEWPORT_DESKTOP = 3;
    public const int TYPE_META_IMAGE = 6;

    public function getParent(): AssetParentInterface;

    public function getAltText(): string;

    public function getSrcset(array|string|null $transformations = null, ?string $extension = null): array;

    public function getSizes(): ?string;

    public function getTransformationNames(): array;
}
