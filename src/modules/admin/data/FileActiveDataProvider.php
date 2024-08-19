<?php

namespace davidhirtz\yii2\media\modules\admin\data;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\skeleton\data\ActiveDataProvider;

/**
 * @property FileQuery $query
 * @extends ActiveDataProvider<File>
 */
class FileActiveDataProvider extends ActiveDataProvider
{
    public ?Folder $folder = null;
    public ?int $type = null;
    public ?string $search = null;

    public function __construct($config = [])
    {
        $this->query = File::find();
        parent::__construct($config);
    }

    protected function prepareQuery(): void
    {
        $this->initQuery();
        parent::prepareQuery();
    }

    protected function initQuery(): void
    {
        if ($this->folder) {
            $this->query = $this->folder->getFiles();
        } else {
            $this->query->with(['folder']);
        }

        $this->query->andFilterWhere(['type' => $this->type])
            ->matching($this->search);
    }

    public function setPagination($value): void
    {
        if (is_array($value)) {
            $value['defaultPageSize'] ??= 20;
        }

        parent::setPagination($value);
    }

    public function setSort($value): void
    {
        if (is_array($value)) {
            $value['defaultOrder'] ??= ['updated_at' => SORT_DESC];
        }

        parent::setSort($value);
    }
}
