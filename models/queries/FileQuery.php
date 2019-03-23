<?php

namespace davidhirtz\yii2\media\models\queries;

/**
 * Class FileQuery
 * @package davidhirtz\yii2\media\models\queries
 */
class FileQuery extends \davidhirtz\yii2\skeleton\db\ActiveQuery
{
    /**
     * @return FileQuery
     */
    public function enabled(): FileQuery
    {
        $model = $this->getModelInstance();
        return $this->andWhere([$model::tableName() . '.status' => $model::STATUS_ENABLED]);
    }

    /**
     * @param string $search
     * @return $this
     */
    public function matching($search): FileQuery
    {
        if ($search = $this->sanitizeSearchString($search)) {
            $tableName = $this->getModelInstance()::tableName();
            $this->andWhere("{$tableName}.[[name]] LIKE :search OR {$tableName}.[[filename]] LIKE :search", [':search' => "%{$search}%"]);
        }

        return $this;
    }
}