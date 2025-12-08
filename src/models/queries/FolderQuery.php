<?php

declare(strict_types=1);

namespace Hirtz\Media\models\queries;

use Hirtz\Media\models\Folder;
use Hirtz\Skeleton\Db\ActiveQuery;

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
