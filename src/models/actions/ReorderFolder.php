<?php

namespace davidhirtz\yii2\media\models\actions;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\models\actions\ReorderActiveRecords;

class ReorderFolder extends ReorderActiveRecords
{
    public function __construct(array $folderIds)
    {
        $sectionEntries = Folder::find()
            ->andWhere(['id' => $folderIds])
            ->orderBy(['position' => SORT_ASC])
            ->all();

        $order = array_flip($folderIds);

        parent::__construct($sectionEntries, $order);
    }
}