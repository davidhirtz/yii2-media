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
use Hirtz\Skeleton\Upload\Upload;
use Hirtz\Skeleton\Web\ContentUploadedFile;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Hirtz\Skeleton\Web\User as WebUser;
use Override;
use Yii;

/**
 * Puts a file into the library and attaches it to a record, as the admin's import does: fetched from a URL by
 * {@see StreamUploadedFile}, with its address policy, size limit and redirect cap, or written from the call's own
 * content by {@see ContentUploadedFile}.
 */
class AttachFileTool extends ResourceTool
{
    /**
     * @var int bytes of decoded content at most; the whole file travels inside one JSON-RPC message
     */
    public int $maxContentSize = 10485760;

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
        return 'Puts a file into the media library and attaches it to a record: downloaded from a public "url", or '
            . 'given as base64 "content" with its "filename", such as a generated image or an SVG. "attributes" are '
            . 'those of the asset linking the two, such as its alt text.';
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
                'content' => ['type' => 'string', 'contentEncoding' => 'base64', 'description' => 'The file itself, instead of "url".'],
                'filename' => ['type' => 'string', 'description' => 'The name of the file given as "content", e.g. "hero.png".'],
                'folder' => ['type' => 'integer', 'description' => 'The folder id, the default folder when it is not given.'],
                'attributes' => ['type' => 'object'],
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
            ...$this->getUploadConfig($arguments),
            'allowedExtensions' => $file->allowedExtensions,
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

    /**
     * @param array<string, mixed> $arguments
     * @return array{class: class-string<ContentUploadedFile|StreamUploadedFile>, ...}
     */
    protected function getUploadConfig(array $arguments): array
    {
        $url = $arguments['url'] ?? null;
        $content = $arguments['content'] ?? null;

        if (is_string($url) === is_string($content)) {
            throw new ToolException('Pass either "url" or "content".');
        }

        if (is_string($url)) {
            if (!Upload::getComponent()->enableStreamUploads) {
                throw new ToolException('Files cannot be fetched from a URL here, pass "content".');
            }

            return ['class' => StreamUploadedFile::class, 'url' => $url];
        }

        $filename = $arguments['filename'] ?? null;

        if (!is_string($filename) || $filename === '') {
            throw new ToolException('"content" needs its "filename".');
        }

        // A data URL is what an image tool often hands over
        $content = (string)preg_replace('/^data:[^,]*;base64,/', '', (string)$content);

        if (strlen($content) * 3 / 4 > $this->maxContentSize) {
            throw new ToolException('The file is larger than ' . $this->maxContentSize . ' bytes.');
        }

        $decoded = base64_decode($content, true);

        if ($decoded === false || $decoded === '') {
            throw new ToolException('"content" is not base64.');
        }

        return ['class' => ContentUploadedFile::class, 'content' => $decoded, 'filename' => $filename];
    }
}
