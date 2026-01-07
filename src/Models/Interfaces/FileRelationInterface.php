<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Interfaces\FileRelationGridContainerInterface;
use Hirtz\Skeleton\Db\ActiveRecord;
use yii\db\ActiveRecordInterface;

/**
 * @phpstan-require-extends ActiveRecord
 */
interface FileRelationInterface extends ActiveRecordInterface
{
    public function getFile(): FileQuery;

    /**
     * @return string[] containing the names of the attributes that should be used to count the files for this relation
     */
    public function getFileCountAttributeNames(): array;

    /**
     * @return class-string<FileRelationGridContainerInterface>
     */
    public function getFileRelationGridContainerClass(): string;
}
