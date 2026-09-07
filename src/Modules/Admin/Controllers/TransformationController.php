<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Skeleton\I18n\Lang;
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

    public function actionIndex(int $id): string|Response
    {
        if (!$file = File::findOne($id)) {
            throw new NotFoundHttpException();
        }

        if (!Yii::$app->getUser()->can(File::AUTH_FILE_UPDATE, ['file' => $file])) {
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

        if (!Yii::$app->getUser()->can(File::AUTH_FILE_UPDATE, ['file' => $transformation->file])) {
            throw new ForbiddenHttpException();
        }

        if ($transformation->delete()) {
            if (Yii::$app->getRequest()->getIsAjax()) {
                return $this->asJson([]);
            }

            $this->success(Lang::t('media', 'TRANSFORMATION_SUCCESS_DELETED'));
            return $this->redirect(['index', 'id' => $transformation->file_id]);
        }

        $errors = $transformation->getFirstErrors();
        throw new ServerErrorHttpException(reset($errors));
    }
}
