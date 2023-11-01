<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\File;
use yii\web\JsExpression;

/**
 * Class FileUpload
 * @package davidhirtz\yii2\media\modules\admin\widgets\forms
 */
class FileUpload extends \davidhirtz\yii2\skeleton\widgets\forms\FileUpload
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        if (!$this->model) {
            $this->model = new File;
        }

        $defaultClientEvents = [
            'fileuploaddone' => new JsExpression("function(){Skeleton.replaceWithAjax('{$this->dropZone}')}"),
        ];

        $this->clientEvents = array_merge($defaultClientEvents, $this->clientEvents);

        parent::init();
    }
}