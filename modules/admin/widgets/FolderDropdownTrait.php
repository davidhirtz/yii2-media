<?php

namespace davidhirtz\yii2\media\modules\admin\widgets;

use davidhirtz\yii2\media\models\Folder;

/**
 * Trait FolderDropdownTrait
 * @package davidhirtz\yii2\media\modules\admin\widgets
 */
trait FolderDropdownTrait
{
    /**
     * @var array
     */
    private $_folders;

    /**
     * @return array
     */
    public function getFolders()
    {
        if ($this->_folders === null) {
            $this->_folders = Folder::find()
                ->select(['name'])
                ->orderBy(['position' => SORT_ASC])
                ->indexBy('id')
                ->column();
        }

        return $this->_folders;
    }
}