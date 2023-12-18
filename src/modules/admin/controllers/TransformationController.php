<?php

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class TransformationController
 * @package davidhirtz\yii2\media\modules\admin\controllers
 *
 * @property Module $module
 */
class TransformationController extends Controller
{
    use ModuleTrait;

    
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['delete'],
                        'roles' => ['fileUpdate'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ]);
    }

    /**
     * @param int $id
     * @return string|Response
     */
    public function actionDelete($id)
    {
        if (!$transformation = Transformation::findOne($id)) {
            throw new NotFoundHttpException();
        }

        if (!Yii::$app->getUser()->can('fileUpdate', ['file' => $transformation->file])) {
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
