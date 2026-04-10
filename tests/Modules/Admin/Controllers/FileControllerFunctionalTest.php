<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Test\Traits\FunctionalTestTrait;
use Hirtz\Skeleton\Test\Traits\UserFixtureTrait;
use Yii;

class FileControllerFunctionalTest extends TestCase
{
    use FunctionalTestTrait;
    use UserFixtureTrait;

    public function testIndexAsGuest(): void
    {
        $this->open('/admin/media/file/index');
        self::assertCurrentUrlEquals('https://www.test.localhost/admin/account/login');
    }

    public function testIndexWithoutPermission(): void
    {
        $user = $this->getUserFromFixture('admin');
        Yii::$app->getUser()->login($user);

        $this->open('/admin/media/file/index');
        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexWithPermission(): void
    {
        $user = $this->getUserFromFixture('admin');
        $this->assignAdminRole($user->id);

        Yii::$app->getUser()->login($user);

        $this->open('/admin/media/file/index');
        self::assertResponseIsSuccessful();
    }
}
