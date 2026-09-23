<?php

declare(strict_types=1);

namespace Hirtz\Media\Console\Controllers;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Handles media module transformations.
 */
class TransformationController extends Controller
{
    use ModuleTrait;

    /**
     * Lists all active and inactive transformations.
     */
    public function actionIndex(): void
    {
        $transformations = FileTransformation::find()
            ->select('COUNT(*)')
            ->groupBy('name')
            ->orderBy('name')
            ->indexBy('name')
            ->column();

        foreach (static::getModule()->getTransformations() as $name => $transformation) {
            $transformations[$name] ??= 0;
        }

        $this->stdout('Transformations:' . PHP_EOL);
        ksort($transformations);

        foreach ($transformations as $name => $count) {
            $this->stdout("  - ");
            $this->stdout("$name  ($count)" . PHP_EOL, !static::getModule()->hasTransformation($name) ? Console::FG_RED : ($count > 0 ? Console::FG_GREEN : null));
        }
    }

    /**
     * Deletes a transformation.
     * @noinspection PhpUnused
     */
    public function actionDelete(string $name): void
    {
        // The name becomes a directory below the upload path. Sanitizing it would delete a different transformation
        // than the one that was asked for, so anything but a plain path segment is refused instead — a lone `.`
        // would resolve to the folder's own upload directory and take every file in it.
        if (!$name || $name !== basename($name) || str_starts_with($name, '.')) {
            $this->stdout("Invalid transformation name \"$name\"" . PHP_EOL, Console::FG_RED);
            return;
        }

        $query = FileTransformation::find()
            ->where(['name' => $name]);

        $fileCount = 0;

        /** @var FileTransformation $transformation */
        foreach ($query->each() as $transformation) {
            $filePath = $transformation->getFilePath();

            if ($transformation->delete()) {
                $fileCount++;

                if ($this->interactive) {
                    $this->stdout(" > Deleted file $filePath" . PHP_EOL);
                }
            }
        }

        $folders = Folder::find()->all();
        $folderCount = 0;

        foreach ($folders as $folder) {
            $path = $folder->getUploadPath() . $name;

            if (is_dir($path)) {
                FileHelper::removeDirectory($path);
                $folderCount++;

                if ($this->interactive) {
                    $this->stdout(" > Removed folder $path" . PHP_EOL);
                }
            }
        }

        if (!$fileCount && !$folderCount) {
            $this->stdout("Nothing found for transformation \"$name\"" . PHP_EOL, Console::FG_YELLOW);
            return;
        }

        $this->stdout("Transformation \"$name\" deleted ($fileCount files, $folderCount folders)" . PHP_EOL, Console::FG_GREEN);
    }
}
