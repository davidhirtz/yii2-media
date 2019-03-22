<?php

namespace davidhirtz\yii2\media\modules\admin\data;

use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use yii\data\ActiveDataProvider;

/**
 * Class FileActiveDataProvider.
 * @package davidhirtz\yii2\media\modules\admin\data
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
     * @var FolderForm
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
     * @inheritdoc
     */
    public function init()
    {
        if ($this->folderId) {
            $this->folder = FolderForm::findOne($this->folderId);
        }

        if (!$this->query) {
            $this->query = $this->folder ? $this->folder->getFiles() : FileForm::find();
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

        parent::init();
    }
}