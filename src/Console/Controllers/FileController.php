<?php

declare(strict_types=1);

namespace Hirtz\Media\Console\Controllers;

use Hirtz\Media\Models\Actions\DeleteFiles;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\ModuleTrait;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Handles media module files.
 */
class FileController extends Controller
{
    use ModuleTrait;

    /**
     * Removes unused files.
     *
     * @noinspection PhpUnused
     */
    public function actionClear(): int
    {
        $query = File::find()
            ->andWhere(['asset_count' => 0])
            ->with('folder');

        $deletedCount = 0;
        $failedCount = 0;

        /** @var File[] $files */
        foreach ($query->batch() as $files) {
            $action = DeleteFiles::create($files);
            $deletedCount += count($action->getDeleted());
            $failedCount += count($action->getFailed());
        }

        $this->stdout("$deletedCount unused files were deleted" . PHP_EOL);

        if ($failedCount) {
            $this->stderr("$failedCount unused files could not be deleted" . PHP_EOL);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Turns the images stored sideways by their EXIF orientation upright.
     *
     * Their width and height are corrected and their transformations deleted, to be written again on request.
     *
     * @noinspection PhpUnused
     */
    public function actionOrient(): void
    {
        $query = File::find()->andWhere(['extension' => static::getModule()->transformableImageExtensions]);
        $orientedCount = 0;

        /** @var File $file */
        foreach ($query->each() as $file) {
            if ($file->orientImage()) {
                $orientedCount++;
            }
        }

        $this->stdout("$orientedCount images were turned upright" . PHP_EOL);
    }
}
