<?php

declare(strict_types=1);

namespace Hirtz\Media\Mcp;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Mcp\Resource;
use Hirtz\Skeleton\Mcp\Tools\ResourceTool;
use Hirtz\Skeleton\Mcp\Tools\ToolException;
use Hirtz\Skeleton\Web\User as WebUser;
use Override;

/**
 * The admin page a person uploads to: a record's images, or a folder of the library. A file in the chat never reaches
 * the server through the model, so the person drops it there, signed in as usual, and the agent carries on with what
 * arrived. No link of its own, so none can leak.
 */
class UploadLinkTool extends ResourceTool
{
    #[Override]
    public function getName(): string
    {
        return 'upload_link';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'Link to upload files';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Answers the admin page where the user uploads their own files, such as photos from the chat, which '
            . 'cannot be passed on otherwise: a record\'s images ("resource" and "id") or a folder of the library. '
            . 'Give the user the URL to open, signed in, and to use its "Upload files" action; then find the files with '
            . 'find_records ("file", '
            . 'or the record\'s asset resource, "order": "-created_at").';
    }

    #[Override]
    public function getResources(): array
    {
        return array_values(array_filter(
            parent::getResources(),
            fn (Resource $resource): bool => !$resource->isReadOnly()
                && is_subclass_of($resource->getModelClass(), AssetModelInterface::class),
        ));
    }

    /**
     * Only beside the `file` resource, through which the agent finds what arrived.
     */
    #[Override]
    public function isAvailable(): bool
    {
        return $this->mcp->getResource('file') !== null && (WebUser::current()?->can(File::AUTH_FILE) ?? false);
    }

    #[Override]
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resource' => $this->getResourceProperty(),
                'id' => ['type' => ['integer', 'string'], 'description' => 'The record the files are attached to.'],
                'folder' => ['type' => 'integer', 'description' => 'The folder id, the default folder when it is not given.'],
            ],
            'additionalProperties' => false,
        ];
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return true;
    }

    #[Override]
    protected function run(array $arguments): array
    {
        if (!$this->isAvailable()) {
            throw new ToolException('Resource not found: file');
        }

        if (isset($arguments['resource'])) {
            $resource = $this->findResource($arguments, true);
            $record = $this->findRecord($resource, $arguments['id'] ?? null);

            if (!$record instanceof AssetModelInterface || !$record->allowsAssets()) {
                throw new ToolException("This {$resource->getName()} takes no files.");
            }

            return [
                'url' => $this->getAbsoluteUrl($record->getAssetClass()::getAdminIndexRoute($record)),
                'record' => $this->getRecordSummary($record),
            ];
        }

        $folder = is_int($arguments['folder'] ?? null) ? Folder::findOne($arguments['folder']) : null;

        if (isset($arguments['folder']) && $folder === null) {
            throw new ToolException('Folder not found: ' . $arguments['folder']);
        }

        return [
            'url' => $this->getAbsoluteUrl(['/admin/media/file/index', 'folder' => $folder?->id]),
            'folder' => $folder?->getAdminName(),
        ];
    }
}
