<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Fixtures;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Test\Fixtures\ActiveFixture;
use yii\test\Fixture;

class FileFixture extends ActiveFixture
{
    /**
     * @var list<class-string<Fixture>>
     */
    public $depends = [
        FolderFixture::class,
    ];

    public $modelClass = File::class;
}
