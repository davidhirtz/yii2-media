<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Queries;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Db\I18nActiveQuery;

/**
 * @extends I18nActiveQuery<File>
 */
class FileQuery extends I18nActiveQuery
{
    public function selectSiteAttributes(): static
    {
        return $this->addSelect($this->prefixColumns(array_diff(
            $this->getModelInstance()->getColumnAttributes(),
            ['name', 'size', 'transformation_count', 'updated_by_user_id', 'created_at']
        )));
    }

    /**
     * The filename is matched as a whole, so `photo.jpg` finds what `basename` alone never did.
     */
    public function matching(?string $search): static
    {
        if ($search = $this->sanitizeSearchString($search)) {
            $tableName = $this->getModelInstance()::tableName();
            $filename = "CONCAT($tableName.[[basename]], '.', $tableName.[[extension]])";

            $this->andWhere("$tableName.[[name]] LIKE :search OR $filename LIKE :search", [
                'search' => "%$search%"
            ]);
        }

        return $this;
    }

    public function withFolder(): static
    {
        return $this->with([
            'folder' => function (FolderQuery $query): void {
                $query->selectSiteAttributes();
            }
        ]);
    }
}
