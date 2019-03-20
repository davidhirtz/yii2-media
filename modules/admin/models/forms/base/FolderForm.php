<?php

namespace davidhirtz\yii2\media\modules\admin\models\forms\base;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;

/**
 * Class FolderForm
 * @package davidhirtz\yii2\media\modules\admin\models\forms\base
 *
 * @method static \davidhirtz\yii2\media\modules\admin\models\forms\FolderForm findOne($condition)
 */
class FolderForm extends Folder
{
    /**
     * @return FileQuery
     */
    public function getFiles(): FileQuery
    {
        return $this->hasMany(FileForm::class, ['folder_id' => 'id'])
            ->indexBy('id')
            ->inverseOf('folder');
    }
}