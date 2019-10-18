<?php
namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\File;
use yii\web\JsExpression;

/**
 * Class FileActiveForm.
 * @package davidhirtz\yii2\media\modules\admin\widgets\forms
 */
class FileUpload extends \davidhirtz\yii2\skeleton\widgets\forms\FileUpload
{
    /**
     * @inheritDoc
     */
    public function init()
    {
        if(!$this->model) {
            $this->model = new File;
        }

        $defaultClientEvents = [
            'fileuploaddone' => new JsExpression('function(){$.get(document.location.href, function(d){$(\''.$this->dropZone.'\').html($(\'<div>\').html(d).find(\''.$this->dropZone.'\').html());if($.timeago!==undefined)$(\'.timeago\').timeago();})}'),
        ];

        $this->clientEvents = array_merge($defaultClientEvents, $this->clientEvents);

        parent::init();
    }
}