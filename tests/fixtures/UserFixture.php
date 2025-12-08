<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\fixtures;

use Hirtz\Skeleton\Models\User;
use yii\test\ActiveFixture;

class UserFixture extends ActiveFixture
{
    public $modelClass = User::class;
}
