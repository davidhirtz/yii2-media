<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Data;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Skeleton\Data\ActiveDataProvider;
use Override;

/**
 * @property FileQuery $query
 * @extends ActiveDataProvider<File>
 */
class FileActiveDataProvider extends ActiveDataProvider
{
    public ?Folder $folder = null;
    public ?string $search = null;
    public ?int $status = null;

    public function __construct($config = [])
    {
        $this->query = File::find();
        parent::__construct($config);
    }

    #[Override]
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

        if (null !== $this->status) {
            $this->query->andWhere(['status' => $this->status]);
        }

        $this->query->matching($this->search);
    }

    #[Override]
    public function setPagination($value): void
    {
        if (is_array($value)) {
            $value['defaultPageSize'] ??= 20;
        }

        parent::setPagination($value);
    }

    #[Override]
    public function setSort($value): void
    {
        if (is_array($value)) {
            $value['defaultOrder'] ??= ['updated_at' => SORT_DESC];
        }

        parent::setSort($value);
    }
}
