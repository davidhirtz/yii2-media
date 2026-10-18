<?php

declare(strict_types=1);

namespace Hirtz\Media\Mcp;

use Hirtz\Media\Models\Actions\InsertAsset;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Mcp\Resource;
use Hirtz\Skeleton\Mcp\Tools\ResourceTool;
use Hirtz\Skeleton\Mcp\Tools\ToolException;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Hirtz\Skeleton\Web\User as WebUser;
use Override;
use Yii;

/**
 * Fetches a file from a URL into the library and attaches it to a record, as the admin's import does. The fetch
 * is {@see StreamUploadedFile}'s, with its address policy, size limit and redirect cap.
 */
class AttachFileTool extends ResourceTool
{
    #[Override]
    public function getName(): string
    {
        return 'attach_file';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'Attach a file from a URL';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Downloads a file from a public URL into the media library and attaches it to a record. "attributes" '
            . 'are those of the asset linking the two, such as its alt text.';
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

    #[Override]
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resource' => $this->getResourceProperty(),
                'id' => ['type' => ['integer', 'string']],
                'url' => ['type' => 'string', 'format' => 'uri'],
                'folder' => ['type' => 'integer', 'description' => 'The folder id, the default folder when it is not given.'],
                'attributes' => ['type' => 'object'],
            ],
            'required' => ['resource', 'id', 'url'],
            'additionalProperties' => false,
        ];
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return false;
    }

    #[Override]
    protected function run(array $arguments): array
    {
        $resource = $this->findResource($arguments, true);
        $record = $this->findRecord($resource, $arguments['id']);

        if (!$record instanceof AssetModelInterface || !$record->allowsAssets()) {
            throw new ToolException("This {$resource->getName()} takes no files.");
        }

        if (!(WebUser::current()?->can(File::AUTH_FILE) ?? false)) {
            throw new ToolException('Resource not found: file');
        }

        $folder = is_int($arguments['folder'] ?? null)
            ? Folder::findOne($arguments['folder'])
            : FolderCollection::getDefault();

        if ($folder === null) {
            throw new ToolException('Folder not found: ' . $arguments['folder']);
        }

        $file = File::create();
        $file->loadDefaultValues();
        $file->populateFolderRelation($folder);
        $file->upload = Yii::createObject([
            'class' => StreamUploadedFile::class,
            'allowedExtensions' => $file->allowedExtensions,
            'url' => $arguments['url'],
        ]);

        if (!$file->insert()) {
            throw new ToolException('The file was not saved.', ['errors' => $file->getErrors()]);
        }

        /** @var array<string, mixed> $attributes */
        $attributes = $arguments['attributes'] ?? [];
        $action = InsertAsset::create($record, $file, $attributes);

        if (!$action->isInserted()) {
            throw new ToolException('The file was saved, but not attached.', [
                'file' => $file->id,
                'errors' => $action->getAsset()->getErrors(),
            ]);
        }

        return [
            'file' => $this->getRecordSummary($file),
            'asset' => $action->getAsset()->id,
            'record' => $this->getRecordSummary($record),
        ];
    }
}
