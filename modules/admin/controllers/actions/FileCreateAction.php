<?php
namespace davidhirtz\yii2\media\modules\admin\controllers\actions;

use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\base\Action;
use yii\web\ForbiddenHttpException;
use yii\web\ServerErrorHttpException;

/**
 * Class FileCreateAction.
 * @package davidhirtz\yii2\media\modules\admin\controllers\actions
 *
 * @property Controller $controller
 */
class FileCreateAction extends Action
{
    /**
     * @var $string
     */
    public $message;

    /**
     * @var array|string
     */
    public $route;

    /**
     * @return string
     */
    public function run()
    {
        if(!Yii::$app->getUser()->can('upload'))
        {
            throw new ForbiddenHttpException;
        }

        $file=new FileForm();

        if($file->insert())
        {
            if(Yii::$app->getRequest()->getIsAjax())
            {
                return $this->controller->renderPartial('@app/modules/content/modules/admin/views/file/_view', [
                    'file'=>$file,
                ]);
            }

            $this->controller->success($this->message ?: Yii::t('media', 'The file was successfully uploaded.'));
        }

        if($file->hasErrors())
        {
            $errors=$file->getFirstErrors();
            throw new ServerErrorHttpException(reset($errors));
        }

        return $this->controller->redirect($this->route);
    }
}