<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Models\Actions\ReorderActiveRecords;
use Hirtz\Skeleton\Models\Trail;
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
        Trail::createOrderTrail(null, Lang::t('media', 'REORDER_FOLDER_FOLDER_ORDER_CHANGED'));
        FolderCollection::invalidateCache();

        parent::afterReorder();
    }
}
