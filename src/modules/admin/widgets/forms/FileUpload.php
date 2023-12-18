<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\File;
use yii\web\JsExpression;

class FileUpload extends \davidhirtz\yii2\skeleton\widgets\forms\FileUpload
{
    public function init(): void
    {
        $this->model ??= File::create();

        $defaultClientEvents = [
            'fileuploaddone' => new JsExpression("function(){Skeleton.replaceWithAjax('$this->dropZone')}"),
        ];

        $this->clientEvents = [...$defaultClientEvents, ...$this->clientEvents];

        parent::init();
    }
}
