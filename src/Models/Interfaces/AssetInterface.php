<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use yii\db\ActiveRecordInterface;

/**
 * @property int $id
 * @property string $model_class
 * @property int $model_id
 * @property int $file_id
 *
 * @property-read AssetModelInterface $model {@see static::getModel()}
 * @property-read File $file {@see static::getFile()}
 *
 * @phpstan-require-extends ActiveRecord
 */
interface AssetInterface extends ActiveRecordInterface, TypeAttributeInterface
{
    public const int TYPE_VIEWPORT_MOBILE = 2;
    public const int TYPE_VIEWPORT_DESKTOP = 3;
    public const int TYPE_META_IMAGE = 6;

    public function getFile(): FileQuery;

    public function getModel(): AssetModelInterface;

    public function getAltText(): string;

    /**
     * @return string|null the `loading` attribute of the rendered image, or null to leave it to the renderer
     */
    public function getLoading(): ?string;

    /**
     * @return string|null the `fetchpriority` attribute of the rendered image, or null to omit it
     */
    public function getFetchPriority(): ?string;

    /**
     * @param list<string>|string|null $transformations
     * @return array<string, string>
     */
    public function getSrcset(array|string|null $transformations = null, ?string $extension = null): array;

    public function getSizes(): ?string;

    /**
     * @return list<string>
     */
    public function getTransformationNames(): array;
}
