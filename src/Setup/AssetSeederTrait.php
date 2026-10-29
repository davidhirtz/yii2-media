<?php

declare(strict_types=1);

namespace Hirtz\Media\Setup;

use Hirtz\Media\Models\Actions\InsertAsset;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Setup\Seeder;
use Hirtz\Skeleton\Web\CopiedUploadedFile;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * A file that cannot be read is reported and costs only its asset, never the record it was declared on.
 *
 * @phpstan-require-extends Seeder
 */
trait AssetSeederTrait
{
    /**
     * Keyed by source, so a file declared twice is stored once.
     *
     * @var array<string, File>
     */
    private array $files = [];

    /**
     * Each asset's attributes, with the `url` or `path` of its file.
     *
     * @param array<array-key, mixed> $assets
     */
    protected function insertAssets(AssetModelInterface&ActiveRecord $model, array $assets): void
    {
        foreach ($assets as $attributes) {
            $attributes = (array)$attributes;
            $url = ArrayHelper::remove($attributes, 'url');
            $path = ArrayHelper::remove($attributes, 'path');

            if (!$model->allowsAssets() || !is_string($url) === !is_string($path)) {
                $asset = Yii::createObject($model->getAssetClass());
                $asset->addError('file_id', $model->allowsAssets()
                    ? 'An asset declares either the url or the path of its file.'
                    : $model::class . ' takes no files.');

                $this->report($asset, false);
                continue;
            }

            $file = is_string($url)
                ? $this->insertFile($url, ['class' => StreamUploadedFile::class, 'url' => $url])
                : $this->insertFile((string)$path, ['class' => CopiedUploadedFile::class, 'path' => Yii::getAlias((string)$path)]);

            if ($file === null) {
                continue;
            }

            $action = InsertAsset::create($model, $file, $attributes);
            $this->report($action->getAsset(), $action->isInserted());
        }
    }

    /**
     * @param array{class: class-string<CopiedUploadedFile|StreamUploadedFile>, ...} $upload
     */
    private function insertFile(string $source, array $upload): ?File
    {
        if (isset($this->files[$source])) {
            return $this->files[$source];
        }

        $file = File::create();
        $file->loadDefaultValues();
        $file->populateFolderRelation(FolderCollection::getDefault());
        $file->upload = Yii::createObject([...$upload, 'allowedExtensions' => $file->allowedExtensions]);

        if (!$file->insert()) {
            if ($file->upload->error === UPLOAD_ERR_NO_FILE) {
                $file->addError('upload', "$source could not be read.");
            }

            $this->report($file, false);
            return null;
        }

        $this->report($file, true);
        return $this->files[$source] = $file;
    }
}
