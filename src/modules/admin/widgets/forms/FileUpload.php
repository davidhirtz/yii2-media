<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\File;
use yii\web\JsExpression;

class FileUpload extends \davidhirtz\yii2\skeleton\widgets\forms\FileUpload
{
    public string $dropZone = '#dropzone';

    #[\Override]
    public function init(): void
    {
        $this->model ??= File::instance();

        $defaultClientEvents = [
            'fileuploaddone' => new JsExpression("function(){Skeleton.replaceWithAjax('$this->dropZone')}"),
        ];

        $this->clientEvents = [...$defaultClientEvents, ...$this->clientEvents];

        parent::init();
    }
}
