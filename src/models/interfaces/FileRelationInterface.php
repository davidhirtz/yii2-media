<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\models\interfaces;

use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use yii\base\Widget;
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
     * @return class-string<Widget>
     */
    public function getFilePanelClass(): string;
}
