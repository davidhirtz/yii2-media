<?php

namespace davidhirtz\yii2\media\models\queries;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\db\I18nActiveQuery;

/**
 * @extends I18nActiveQuery<File>
 */
class FileQuery extends I18nActiveQuery
{
    public function selectSiteAttributes(): static
    {
        return $this->addSelect($this->prefixColumns(array_diff(
            $this->getModelInstance()->attributes(),
            ['name', 'size', 'transformation_count', 'updated_by_user_id', 'created_at']
        )));
    }

    public function matching(?string $search): static
    {
        if ($search = $this->sanitizeSearchString($search)) {
            $tableName = $this->getModelInstance()::tableName();
            $this->andWhere("$tableName.[[name]] LIKE :search OR $tableName.[[basename]] LIKE :search", [
                'search' => "%$search%"
            ]);
        }

        return $this;
    }

    public function withFolder(): static
    {
        return $this->with([
            'folder' => function (FolderQuery $query) {
                $query->selectSiteAttributes();
            }
        ]);
    }
}
