<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Fixtures;

use Hirtz\Media\Models\File;
use yii\test\ActiveFixture;

class FileFixture extends ActiveFixture
{
    public $depends = [
        FolderFixture::class,
    ];

    public $modelClass = File::class;
}
