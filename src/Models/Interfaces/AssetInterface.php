<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use yii\db\ActiveRecordInterface;

/**
 * @template TModel of AssetModelInterface = AssetModelInterface
 *
 * @property int $id
 * @property string $model_class
 * @property int $model_id
 * @property int $file_id
 *
 * @property-read TModel $model {@see static::getModel()}
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

    /**
     * @return TModel
     */
    public function getModel(): AssetModelInterface;

    public function getAltText(): string;

    /**
     * Null leaves the `loading` attribute to the renderer.
     */
    public function getLoading(): ?string;

    /**
     * Null omits the `fetchpriority` attribute.
     */
    public function getFetchPriority(): ?string;

    /**
     * @param list<string>|string|null $transformations
     * @return array<int, string>
     */
    public function getSrcset(array|string|null $transformations = null, ?string $extension = null): array;

    public function getSizes(): ?string;

    /**
     * @return list<string>
     */
    public function getTransformationNames(): array;
}
