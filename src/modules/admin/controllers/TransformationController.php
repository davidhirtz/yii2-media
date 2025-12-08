<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\models\File;
use Hirtz\Media\models\Transformation;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Media\modules\ModuleTrait;
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
 * @property Module $module
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
                        'actions' => ['delete'],
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

            $this->success(Yii::t('media', 'The transformation was deleted.'));
            return $this->redirect(['file/update', 'id' => $transformation->file_id]);
        }

        $errors = $transformation->getFirstErrors();
        throw new ServerErrorHttpException(reset($errors));
    }
}
