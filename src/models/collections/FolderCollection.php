<?php

namespace davidhirtz\yii2\media\models\collections;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;

class FolderCollection
{
    use ModuleTrait;

    private static ?array $_folders = null;

    /**
     * @return Folder[]
     */
    public static function getAll(): array
    {
        static::$_folders ??= Folder::find()
            ->orderBy(static::getModule()->defaultFolderOrder)
            ->indexBy('id')
            ->all();

        return static::$_folders;
    }
}