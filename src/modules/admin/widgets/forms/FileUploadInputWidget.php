<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\ModuleTrait;
use Override;

/**
 * @property File|null $model
 */
class FileUploadInputWidget extends \davidhirtz\yii2\skeleton\widgets\forms\FileUploadInputWidget
{
    use ModuleTrait;

    #[Override]
    public function init(): void
    {
        $this->model ??= File::instance();

        $this->options['accept'] ??= $this->getAcceptMimeTypesFromModule();
        //        $defaultClientEvents = [
        //            'fileuploaddone' => new JsExpression("function(){Skeleton.replaceWithAjax('$this->dropZone')}"),
        //        ];
        //
        //        $this->clientEvents = [...$defaultClientEvents, ...$this->clientEvents];

        parent::init();
    }

    protected function getAcceptMimeTypesFromModule(): string
    {
        $extensions = array_map(fn (string $value): string => ".$value", static::getModule()->allowedExtensions);
        return implode(',', $extensions);
    }
}
