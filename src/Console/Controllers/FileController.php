<?php

declare(strict_types=1);

namespace Hirtz\Media\Console\Controllers;

use Hirtz\Media\Models\Actions\DeleteFiles;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\ModuleTrait;
use Override;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Handles media module files.
 */
class FileController extends Controller
{
    use ModuleTrait;

    /**
     * @var bool whether to list the unused files without deleting them
     */
    public bool $dryRun = false;

    #[Override]
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'delete-unused') {
            $options[] = 'dryRun';
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function optionAliases(): array
    {
        return [...parent::optionAliases(), 'd' => 'dryRun'];
    }

    /**
     * Deletes every file no asset references, after one confirmation.
     *
     * @noinspection PhpUnused
     */
    public function actionDeleteUnused(): int
    {
        $query = File::find()
            ->andWhere(['asset_count' => 0])
            ->with('folder');

        $count = (int)$query->count();

        if (!$count) {
            $this->stdout('No unused files found' . PHP_EOL, Console::FG_YELLOW);
            return ExitCode::OK;
        }

        if ($this->dryRun) {
            /** @var File $file */
            foreach ($query->each() as $file) {
                $this->stdout(" > {$file->getFilePath()}" . PHP_EOL);
            }

            $this->stdout("$count unused files would be deleted" . PHP_EOL);
            return ExitCode::OK;
        }

        if (!$this->confirm("Delete $count unused files?")) {
            return ExitCode::OK;
        }

        $deletedCount = 0;
        $failedCount = 0;

        /** @var File[] $files */
        foreach ($query->batch() as $files) {
            $action = DeleteFiles::create($files);
            $deletedCount += count($action->getDeleted());
            $failedCount += count($action->getFailed());
        }

        $this->stdout("$deletedCount unused files were deleted" . PHP_EOL, Console::FG_GREEN);

        if ($failedCount) {
            $this->stderr("$failedCount unused files could not be deleted" . PHP_EOL, Console::FG_RED);
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
