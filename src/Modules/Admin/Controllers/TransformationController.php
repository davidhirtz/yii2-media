<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Transformation;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Web\Controller;
use Override;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * @extends Controller<Module>
 */
class TransformationController extends Controller
{
    use ModuleTrait;

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
                        'actions' => ['index', 'delete'],
                        'roles' => [File::AUTH_FILE_UPDATE],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(int $file): string|Response
    {
        if (!$file = File::findOne($file)) {
            throw new NotFoundHttpException();
        }

        if (!$this->webuser->can(File::AUTH_FILE_UPDATE, ['file' => $file])) {
            throw new ForbiddenHttpException();
        }

        return $this->render('index', [
            'file' => $file,
        ]);
    }

    public function actionDelete(int $id): string|Response
    {
        if (!$transformation = Transformation::findOne($id)) {
            throw new NotFoundHttpException();
        }

        if (!$this->webuser->can(File::AUTH_FILE_UPDATE, ['file' => $transformation->file])) {
            throw new ForbiddenHttpException();
        }

        if ($transformation->delete()) {
            if ($this->request->getIsAjax()) {
                return $this->asJson([]);
            }

            $this->success(Yii::t('media', 'TRANSFORMATION_SUCCESS_DELETED'));
            return $this->redirect(['index', 'file' => $transformation->file_id]);
        }

        $errors = $transformation->getFirstErrors();
        throw new ServerErrorHttpException(reset($errors));
    }
}
