<?php

namespace davidhirtz\yii2\media\modules\admin\data;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use yii\data\ActiveDataProvider;

/**
 * @property FileQuery $query
 */
class FileActiveDataProvider extends ActiveDataProvider
{
    public ?Folder $folder = null;
    public ?int $type = null;
    public ?string $search = null;

    public function init(): void
    {
        $this->initQuery();
        parent::init();
    }

    public function initQuery(): void
    {
        $this->query ??= $this->folder?->getFiles() ?? File::find();

        $this->query->andFilterWhere(['type' => $this->type])
            ->matching($this->search);
        
        if (!$this->folder) {
            $this->query->with([
                'folder' => function (ActiveQuery $query) {
                    $query->select(['id', 'name', 'path']);
                }
            ]);
        }

        $this->setPagination(['defaultPageSize' => 20]);
        $this->setSort(['defaultOrder' => ['updated_at' => SORT_DESC]]);
    }
}
