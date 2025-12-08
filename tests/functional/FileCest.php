<?php

declare(strict_types=1);

/**
 * @noinspection PhpUnused
 */

namespace Hirtz\Media\tests\functional;

use Hirtz\Media\models\File;
use Hirtz\Media\modules\admin\data\FileActiveDataProvider;
use Hirtz\Media\modules\admin\widgets\grids\FileGridView;
use Hirtz\Media\tests\support\FunctionalTester;
use Hirtz\Skeleton\codeception\fixtures\UserFixtureTrait;
use Hirtz\Skeleton\codeception\functional\BaseCest;
use Hirtz\Skeleton\models\User;
use Hirtz\Skeleton\modules\admin\widgets\forms\LoginActiveForm;
use Yii;

class FileCest extends BaseCest
{
    use UserFixtureTrait;

    public function checkIndexAsGuest(FunctionalTester $I): void
    {
        $I->amOnPage('/admin/file/index');

        $widget = Yii::createObject(LoginActiveForm::class);
        $I->seeElement("#$widget->id");
    }

    public function checkIndexWithoutPermission(FunctionalTester $I): void
    {
        $this->getLoggedInUser();

        $I->amOnPage('/admin/file/index');
        $I->seeResponseCodeIs(403);
    }

    public function checkIndexWithPermission(FunctionalTester $I): void
    {
        $user = $this->getLoggedInUser();
        $this->assignPermission($user->id, File::AUTH_FILE_UPDATE);
        $I->amOnPage('/admin/file/index');

        $widget = Yii::$container->get(FileGridView::class, [], [
            'dataProvider' => Yii::createObject(FileActiveDataProvider::class),
        ]);

        $I->seeElement("#$widget->id");
    }

    protected function getLoggedInUser(): User
    {
        $webuser = Yii::$app->getUser();
        $webuser->loginType = 'test';

        $user = User::find()->one();

        $webuser->login($user);
        return $user;
    }
}
