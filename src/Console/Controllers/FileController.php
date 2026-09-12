<?php

declare(strict_types=1);

namespace Hirtz\Media\Console\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\ModuleTrait;
use yii\console\Controller;

/**
 * Handles media module files
 */
class FileController extends Controller
{
    use ModuleTrait;

    /**
     * Removes unused files
     * @noinspection PhpUnused
     */
    public function actionClear(): void
    {
        $query = File::find()->andWhere(['asset_count' => 0]);
        $deletedCount = 0;

        /** @var File $file */
        foreach ($query->each() as $file) {
            if ($file->delete()) {
                $deletedCount++;
            }
        }

        $this->stdout("$deletedCount unused files were deleted" . PHP_EOL);
    }
}
