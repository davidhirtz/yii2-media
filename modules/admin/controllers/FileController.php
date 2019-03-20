<?php

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Sort;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

/**
 * Class BaseFileController.
 * @package davidhirtz\yii2\media\modules\admin\controllers
 * @see FileController
 *
 * @property \davidhirtz\yii2\media\modules\admin\Module $module
 */
class FileController extends Controller
{
    use ModuleTrait;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['create', 'index', 'order', 'update', 'delete'],
                        'roles' => ['media'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'order' => ['post'],
                    'upload' => ['post'],
                ],
            ],
        ]);
    }

    /**
     * @param int $id
     * @param int $type
     * @param string $q
     * @return string
     */
    public function actionIndex($id = null, $type = null, $q = null)
    {
        $file = $id ? FileForm::findOne($id) : null;

        $query = $this->getQuery()
            ->andFilterWhere(['type' => $type])
            ->orderBy(['position' => SORT_ASC])
            ->matching($q);

        if ($this->getModule()->defaultFileSort) {
            $query->orderBy($this->getModule()->defaultFileSort);
        }

        if ($file && $file->order_by) {
            $query->orderBy($file->order_by);
        }

        $provider = new FileActiveDataProvider([
            'query' => $query,
        ]);

        /** @noinspection MissedViewInspection */
        return $this->render('index', [
            'provider' => $provider,
            'file' => $file,
        ]);
    }

    /**
     * @param int $id
     * @param int $type
     * @return string|\yii\web\Response
     */
    public function actionCreate($id = null, $type = null)
    {
        $file = new FileForm;

        $file->parent_id = $id;
        $file->type = $type;

        if ($file->load(Yii::$app->getRequest()->post()) && $file->insert()) {
            $this->success(Yii::t('media', 'The file was created.'));
            return $this->redirect(['index']);
        }

        /** @noinspection MissedViewInspection */
        return $this->render('create', [
            'file' => $file,
        ]);
    }

    /**
     * @param int $id
     * @return string|\yii\web\Response
     */
    public function actionUpdate($id)
    {
        if (!$file = FileForm::findOne($id)) {
            throw new NotFoundHttpException;
        }

        if ($file->load(Yii::$app->getRequest()->post()) && $file->update()) {
            $this->success(Yii::t('media', 'The file was updated.'));
            return $this->refresh();
        }

        /** @noinspection MissedViewInspection */
        return $this->render('update', [
            'file' => $file,
        ]);
    }

    /**
     * @param int $id
     * @return string|\yii\web\Response
     */
    public function actionDelete($id)
    {
        if (!$file = File::findOne($id)) {
            throw new NotFoundHttpException;
        }

        if ($file->delete()) {
            $this->success(Yii::t('media', 'The file was deleted.'));
            return $this->redirect(['index']);
        }

        $errors = $file->getFirstErrors();
        throw new ServerErrorHttpException(reset($errors));
    }

    /**
     * @param int $id
     */
    public function actionOrder($id = null)
    {
        $files = File::find()->select(['id', 'position'])
            ->filterWhere(['parent_id' => $id])
            ->orderBy(['position' => SORT_ASC])
            ->all();

        File::updatePosition($files, array_flip(Yii::$app->getRequest()->post('file')));
    }

    /**
     * @return Sort
     */
    protected function getSort(): Sort
    {
        return new Sort([
            'attributes' => [
                'type' => [
                    'asc' => ['type' => SORT_ASC, 'name' => SORT_ASC],
                    'desc' => ['type' => SORT_DESC, 'name' => SORT_DESC],
                ],
                'name' => [
                    'asc' => ['name' => SORT_ASC],
                    'desc' => ['name' => SORT_DESC],
                ],
                'file_count' => [
                    'asc' => ['file_count' => SORT_ASC, 'name' => SORT_ASC],
                    'desc' => ['file_count' => SORT_DESC, 'name' => SORT_ASC],
                    'default' => SORT_DESC,
                ],
                'section_count' => [
                    'asc' => ['section_count' => SORT_ASC, 'name' => SORT_ASC],
                    'desc' => ['section_count' => SORT_DESC, 'name' => SORT_ASC],
                    'default' => SORT_DESC,
                ],
                'publish_date' => [
                    'asc' => ['publish_date' => SORT_ASC],
                    'desc' => ['publish_date' => SORT_DESC],
                    'default' => SORT_DESC,
                ],
                'updated_at' => [
                    'asc' => ['updated_at' => SORT_ASC],
                    'desc' => ['updated_at' => SORT_DESC],
                    'default' => SORT_DESC,
                ],
            ],
        ]);
    }

    /**
     * @return FileQuery
     */
    protected function getQuery()
    {
        return FileForm::find()->replaceI18nAttributes();
    }
}