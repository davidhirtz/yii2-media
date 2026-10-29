<?php

declare(strict_types=1);

namespace Hirtz\Media\Mcp;

use Hirtz\Media\Models\Actions\ReorderAssets;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Mcp\Resource;
use Hirtz\Skeleton\Models\Interfaces\AdminModelInterface;

/**
 * Created by `attach_file` alone, and never moved to another owner or file.
 */
class AssetResources
{
    /**
     * @param class-string<Asset> $assetClass
     * @return Resource<Asset>
     */
    public static function make(string $name, string $assetClass, string $parentParam): Resource
    {
        /** @var class-string<ActiveRecord&AdminModelInterface> $parentClass */
        $parentClass = $assetClass::getModelClass();

        return Resource::make($name, $assetClass)
            ->creatable(false)
            ->parent($parentParam, $parentClass, static fn (): bool => false)
            ->exceptAttributes('model_class', 'model_id', 'file_id')
            ->reorder(static fn (?ActiveRecord $parent, array $ids): bool => $parent instanceof AssetModelInterface
                && (new ReorderAssets($parent, $ids))->run() !== false);
    }
}
