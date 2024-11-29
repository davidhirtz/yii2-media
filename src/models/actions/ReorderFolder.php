<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\models\actions;

use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\models\actions\ReorderActiveRecords;
use davidhirtz\yii2\skeleton\models\Trail;
use Yii;

class ReorderFolder extends ReorderActiveRecords
{
    public function __construct(array $folderIds)
    {
        $folders = Folder::find()
            ->andWhere(['id' => $folderIds])
            ->orderBy(['position' => SORT_ASC])
            ->all();

        $order = array_flip($folderIds);

        parent::__construct($folders, $order);
    }

    protected function afterReorder(): void
    {
        Trail::createOrderTrail(null, Yii::t('media', 'Folder order changed'));
        FolderCollection::invalidateCache();

        parent::afterReorder();
    }
}
