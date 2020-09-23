<?php

namespace davidhirtz\yii2\media\models\queries;

use davidhirtz\yii2\media\models\File;

/**
 * Class FileQuery
 * @package davidhirtz\yii2\media\models\queries
 *
 * @method File[] all ($db = null)
 * @method File one($db = null)
 */
class FileQuery extends \davidhirtz\yii2\skeleton\db\ActiveQuery
{
    /**
     * @return FileQuery
     */
    public function selectSiteAttributes()
    {
        return $this->addSelect(array_diff($this->getModelInstance()->attributes(),
            ['name', 'size', 'transformation_count', 'updated_by_user_id', 'created_at']));
    }

    /**
     * @param string $search
     * @return FileQuery
     */
    public function matching($search): FileQuery
    {
        if ($search = $this->sanitizeSearchString($search)) {
            $tableName = $this->getModelInstance()::tableName();
            $this->andWhere("{$tableName}.[[name]] LIKE :search OR {$tableName}.[[basename]] LIKE :search", [':search' => "%{$search}%"]);
        }

        return $this;
    }

    /**
     * @return FileQuery
     */
    public function withFolder()
    {
        return $this->with([
            'folder' => function (FolderQuery $query) {
                $query->selectSiteAttributes();
            }
        ]);
    }
}