<?php

declare(strict_types=1);

namespace Hirtz\Media\Console\Controllers;

use FilesystemIterator;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Handles media module transformations.
 */
class TransformationController extends Controller
{
    use ModuleTrait;

    /**
     * @var string limits the deletes to the transformations written in this extension, such as `jpg`
     */
    public string $extension = '';

    /**
     * @var int the seconds to sleep after every batch of deleted records, so a huge delete does not exhaust the
     * database, `0` to disable
     */
    public int $sleep = 1;

    /**
     * @var int the number of records deleted between two sleeps
     */
    public int $batchSize = 100;

    /**
     * @var bool whether to list what would be deleted without deleting it
     */
    public bool $dryRun = false;

    #[Override]
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if (in_array($actionID, ['delete', 'delete-all', 'delete-unused'], true)) {
            $options = [...$options, 'extension', 'sleep', 'dryRun'];
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

        foreach (static::getModule()->getTransformationNames() as $name) {
            $transformations[$name] ??= 0;
        }

        $this->stdout('Transformations:' . PHP_EOL);
        ksort($transformations);

        foreach ($transformations as $name => $count) {
            $this->stdout("  - ");
            $this->stdout("$name  ($count)" . PHP_EOL, !static::getModule()->hasTransformation((string)$name) ? Console::FG_RED : ($count > 0 ? Console::FG_GREEN : null));
        }
    }

    /**
     * Deletes every transformation the module no longer configures.
     * @noinspection PhpUnused
     */
    public function actionDeleteUnused(): int
    {
        if (!$this->isValidExtension()) {
            return ExitCode::USAGE;
        }

        $module = static::getModule();

        $names = FileTransformation::find()
            ->select('name')
            ->distinct()
            ->andFilterWhere(['extension' => $this->extension])
            ->orderBy('name')
            ->column();

        $names = array_values(array_filter(
            array_map(strval(...), $names),
            fn (string $name): bool => !$module->hasTransformation($name)
        ));

        if (!$names) {
            $this->stdout('No unused transformations found' . PHP_EOL, Console::FG_YELLOW);
            return ExitCode::OK;
        }

        if (!$this->dryRun && !$this->confirm('Delete unused transformations ' . implode(', ', $names) . $this->getExtensionLabel() . '?')) {
            return ExitCode::OK;
        }

        return $this->deleteTransformations($names);
    }

    /**
     * Deletes every transformation, configured or not, so each is regenerated on demand.
     * @noinspection PhpUnused
     */
    public function actionDeleteAll(): int
    {
        if (!$this->isValidExtension()) {
            return ExitCode::USAGE;
        }

        $names = FileTransformation::find()
            ->select('name')
            ->distinct()
            ->andFilterWhere(['extension' => $this->extension])
            ->column();

        // A configured name may have directories left without records
        $names = array_unique([
            ...array_map(strval(...), $names),
            ...static::getModule()->getTransformationNames(),
        ]);

        sort($names);

        if (!$this->dryRun && !$this->confirm('Delete all transformations ' . implode(', ', $names) . $this->getExtensionLabel() . '?')) {
            return ExitCode::OK;
        }

        return $this->deleteTransformations($names);
    }

    /**
     * @param list<string> $names
     */
    private function deleteTransformations(array $names): int
    {
        $exitCode = ExitCode::OK;

        foreach ($names as $name) {
            $exitCode = max($exitCode, $this->actionDelete($name));
        }

        return $exitCode;
    }

    /**
     * Deletes a transformation.
     * @noinspection PhpUnused
     */
    public function actionDelete(string $name): int
    {
        if (!$name || $name !== basename($name) || str_starts_with($name, '.')) {
            $this->stderr("Invalid transformation name \"$name\"" . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        if (!$this->isValidExtension()) {
            return ExitCode::USAGE;
        }

        $query = FileTransformation::find()
            ->where(['name' => $name])
            ->andFilterWhere(['extension' => $this->extension]);

        $fileCount = 0;

        // A dry run deletes no record, so the directory scan below must not count their files again
        $filePaths = [];

        /** @var FileTransformation $transformation */
        foreach ($query->each($this->batchSize) as $transformation) {
            $filePath = FileHelper::normalizePath($transformation->getFilePath());

            if ($this->dryRun || $transformation->delete()) {
                $fileCount++;
                $filePaths[$filePath] = true;

                $this->writeDeleted("file $filePath");

                if (!$this->dryRun && $this->sleep > 0 && $fileCount % $this->batchSize === 0) {
                    sleep($this->sleep);
                }
            }
        }

        $folders = Folder::find()->all();
        $folderCount = 0;

        foreach ($folders as $folder) {
            $path = $folder->getUploadPath() . $name;

            if (!is_dir($path)) {
                continue;
            }

            if ($this->extension !== '') {
                // A file without a record is what this is run to clean up as well, the directory goes once it is empty
                $files = FileHelper::findFiles($path, [
                    'only' => ["*.$this->extension"],
                    'caseSensitive' => false,
                    'recursive' => false,
                ]);

                foreach ($files as $filePath) {
                    if (isset($filePaths[$filePath])) {
                        continue;
                    }

                    if ($this->dryRun || FileHelper::unlink($filePath)) {
                        $fileCount++;
                        $filePaths[$filePath] = true;

                        $this->writeDeleted("file $filePath");
                    }
                }

                if (!$this->isEmptiedBy($path, $filePaths)) {
                    continue;
                }
            }

            if (!$this->dryRun) {
                FileHelper::removeDirectory($path);
            }

            $folderCount++;
            $this->writeDeleted("folder $path");
        }

        $label = $this->getExtensionLabel();

        if (!$fileCount && !$folderCount) {
            $this->stdout("Nothing found for transformation \"$name\"$label" . PHP_EOL, Console::FG_YELLOW);
            return ExitCode::OK;
        }

        if ($this->dryRun) {
            $this->stdout("Transformation \"$name\"$label would be deleted ($fileCount files, $folderCount folders)" . PHP_EOL);
            return ExitCode::OK;
        }

        $this->stdout("Transformation \"$name\"$label deleted ($fileCount files, $folderCount folders)" . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    private function writeDeleted(string $subject): void
    {
        if ($this->dryRun) {
            $this->stdout(" > Would delete $subject" . PHP_EOL);
        } elseif ($this->interactive) {
            $this->stdout(" > Deleted $subject" . PHP_EOL);
        }
    }

    /**
     * @param array<string, true> $filePaths
     */
    private function isEmptiedBy(string $path, array $filePaths): bool
    {
        foreach (new FilesystemIterator($path, FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS) as $pathname) {
            if (!isset($filePaths[FileHelper::normalizePath((string)$pathname)])) {
                return false;
            }
        }

        return true;
    }

    private function isValidExtension(): bool
    {
        if ($this->extension === '' || ctype_alnum($this->extension)) {
            return true;
        }

        $this->stderr("Invalid extension \"$this->extension\"" . PHP_EOL, Console::FG_RED);
        return false;
    }

    private function getExtensionLabel(): string
    {
        return $this->extension !== '' ? " ($this->extension)" : '';
    }
}
