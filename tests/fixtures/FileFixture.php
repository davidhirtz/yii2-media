<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\fixtures;

use Hirtz\Media\models\File;
use yii\test\ActiveFixture;

class FileFixture extends ActiveFixture
{
    public $modelClass = File::class;
}
