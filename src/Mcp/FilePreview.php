<?php

declare(strict_types=1);

namespace Hirtz\Media\Mcp;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\ModuleTrait;
use Throwable;
use Yii;

/**
 * Read off the stored file, never its public URL: a draft is not public, and a remote client cannot fetch one anyway.
 */
class FilePreview
{
    use ModuleTrait;

    /**
     * @return array{data: string, mimeType: string}|null
     */
    public static function create(File $file): ?array
    {
        $size = static::getModule()->mcpPreviewSize;

        if (!$size || !$file->isTransformableImage()) {
            return null;
        }

        // A JPEG would turn transparency black
        $extension = in_array($file->extension, ['gif', 'png', 'webp'], true) ? 'png' : 'jpg';

        try {
            $image = static::getModule()->getImageProcessor()->read($file->getFilePath())->scaleDown($size, $size);
            $data = (string)($extension === 'png' ? $image->encodeUsingFileExtension('png') : $image->encodeUsingFileExtension('jpg', quality: 80));
        } catch (Throwable $exception) {
            Yii::warning("No MCP preview of file $file->id: {$exception->getMessage()}", __METHOD__);
            return null;
        }

        return [
            'data' => base64_encode($data),
            'mimeType' => $extension === 'png' ? 'image/png' : 'image/jpeg',
        ];
    }
}
