<?php

namespace davidhirtz\yii2\media\modules\admin\data\base;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use yii\data\ActiveDataProvider;

/**
 * Class FileActiveDataProvider
 * @package davidhirtz\yii2\media\modules\admin\data\base
 *
 * @property FileQuery $query
 */
class FileActiveDataProvider extends ActiveDataProvider
{
    /**
     * @var int
     */
    public $folderId;

    /**
     * @var Folder
     */
    public $folder;

    /**
     * @var int
     */
    public $type;

    /**
     * @var string
     */
    public $search;

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->initQuery();
        parent::init();
    }
    /**
     * Inits query.
     */
    public function initQuery()
    {
        if ($this->folderId) {
            $this->folder = Folder::findOne($this->folderId);
        }

        if (!$this->query) {
            $this->query = $this->folder ? $this->folder->getFiles() : File::find();
        }

        $this->query->andFilterWhere(['type' => $this->type])
            ->matching($this->search);
        
        if (!$this->folder) {
            $this->query->with([
                'folder' => function (ActiveQuery $query) {
                    $query->select(['id', 'name', 'path']);
                }
            ]);
        }

        $this->setPagination(['defaultPageSize' => 50]);
        $this->setSort(['defaultOrder' => ['updated_at' => SORT_DESC]]);
    }
}