<?php

namespace davidhirtz\yii2\media\models\queries;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\db\ActiveQuery;

/**
 * @extends ActiveQuery<Folder>
 */
class FolderQuery extends ActiveQuery
{
    public function selectSiteAttributes(): static
    {
        return $this->addSelect(['id', 'path']);
    }

    public function matching(?string $search): static
    {
        if ($search = $this->sanitizeSearchString($search)) {
            $model = $this->getModelInstance();
            $tableName = $model::tableName();

            $this->andWhere("$tableName.[[name]] LIKE :search", [
                'search' => "%$search%",
            ]);
        }

        return $this;
    }
}
