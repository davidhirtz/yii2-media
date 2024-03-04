<?php

/**
 * @noinspection PhpUnused
 */

namespace davidhirtz\yii2\media\tests\functional;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\admin\widgets\grids\FileGridView;
use davidhirtz\yii2\media\tests\support\FunctionalTester;
use davidhirtz\yii2\skeleton\codeception\fixtures\UserFixtureTrait;
use davidhirtz\yii2\skeleton\codeception\functional\BaseCest;
use davidhirtz\yii2\skeleton\models\User;
use davidhirtz\yii2\skeleton\modules\admin\widgets\forms\LoginActiveForm;
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

        $widget = Yii::$container->get(FileGridView::class, [], [
            'dataProvider' => Yii::createObject(FileActiveDataProvider::class),
        ]);

        $I->amOnPage('/admin/file/index');
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
