<?php

declare(strict_types=1);

namespace Hirtz\Media\Mcp;

use Hirtz\Media\Models\Asset;
use Hirtz\Skeleton\Mcp\Resource;
use Hirtz\Skeleton\Mcp\Tools\ResourceTool;
use Hirtz\Skeleton\Mcp\Tools\ToolException;
use Override;

/**
 * Removes a file from the record it is attached to; the file stays in the library.
 */
class DetachFileTool extends ResourceTool
{
    #[Override]
    public function getName(): string
    {
        return 'detach_file';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'Detach a file';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Removes an asset, a file attached to a record, from that record (find_records with "filter" '
            . '{"model_id": <record id>}). The file stays in the media library.';
    }

    #[Override]
    public function getResources(): array
    {
        return array_values(array_filter(
            parent::getResources(),
            fn (Resource $resource): bool => !$resource->isReadOnly() && is_a($resource->getModelClass(), Asset::class, true),
        ));
    }

    #[Override]
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resource' => $this->getResourceProperty(),
                'id' => ['type' => ['integer', 'string']],
            ],
            'required' => ['resource', 'id'],
            'additionalProperties' => false,
        ];
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return false;
    }

    #[Override]
    public function isDestructive(): bool
    {
        return true;
    }

    #[Override]
    protected function run(array $arguments): array
    {
        $resource = $this->findResource($arguments, true);
        $asset = $this->findRecord($resource, $arguments['id']);

        if (!$asset instanceof Asset || $asset->delete() === false) {
            throw new ToolException("The {$resource->getName()} was not detached.");
        }

        return [
            'detached' => $asset->id,
            'file' => $asset->file_id,
        ];
    }
}
