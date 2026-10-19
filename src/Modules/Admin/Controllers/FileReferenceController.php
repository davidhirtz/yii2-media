<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Controllers\Traits\FileControllerTrait;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Skeleton\Web\Controller;
use Override;
use yii\filters\AccessControl;
use yii\web\Response;

/**
 * The records whose custom attributes use one file. A use is removed by editing its record or by deleting the file.
 *
 * @extends Controller<Module>
 */
class FileReferenceController extends Controller
{
    use FileControllerTrait;

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index'],
                        'roles' => [File::AUTH_FILE],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex(int $file): Response|string
    {
        return $this->render('index', [
            'file' => $this->findFile($file),
        ]);
    }
}
