<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Override;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FolderControllerTest extends TestCase
{
    #[Override]
    public function fixtures(): array
    {
        return [
            'folder' => FolderFixture::class,
            'file' => FileFixture::class,
            'user' => UserFixture::class,
        ];
    }

    public function testIndexListsTheFolders(): void
    {
        $this->login();
        $this->createFolder('Archive', 'archive');

        $html = Yii::$app->runAction('admin/media/folder/index');

        self::assertIsString($html);
        self::assertStringContainsString('Default', $html);
        self::assertStringContainsString('Archive', $html);
    }

    public function testIndexSearchesTheName(): void
    {
        $this->login();
        $this->createFolder('Archive', 'archive');

        $html = Yii::$app->runAction('admin/media/folder/index', ['q' => 'Archi']);

        self::assertIsString($html);
        self::assertStringContainsString('Archive', $html);
        self::assertStringNotContainsString('>Default<', $html);
    }

    public function testIndexIsForbiddenWithoutThePermission(): void
    {
        Yii::$app->getUser()->setIdentity($this->getUserFromFixture('admin'));

        $this->expectException(ForbiddenHttpException::class);
        Yii::$app->runAction('admin/media/folder/index');
    }

    public function testCreateRendersTheForm(): void
    {
        $this->login();

        $html = Yii::$app->runAction('admin/media/folder/create');

        self::assertIsString($html);
        self::assertStringContainsString('name="Folder[name]"', $html);
    }

    public function testCreateInsertsTheFolder(): void
    {
        $this->login();

        $response = $this->post('admin/media/folder/create', [], [
            'Folder' => ['name' => 'Archive', 'path' => 'archive'],
        ]);

        self::assertInstanceOf(Response::class, $response);
        self::assertNotNull(Folder::findOne(['path' => 'archive']));
        self::assertNotEmpty(Yii::$app->getSession()->getFlash('success'));
    }

    /**
     * The path becomes a directory under the upload root, so it is a single segment of safe characters.
     */
    public function testAPathThatIsNotASingleSegmentIsRefused(): void
    {
        $this->login();

        foreach (['../escape', 'with/slash', 'with space'] as $path) {
            $html = $this->post('admin/media/folder/create', [], [
                'Folder' => ['name' => 'Bad', 'path' => $path],
            ]);

            self::assertIsString($html, "Accepted $path");
            self::assertNull(Folder::findOne(['path' => $path]));
        }
    }

    public function testUpdateSavesTheFolder(): void
    {
        $this->login();

        $response = $this->post('admin/media/folder/update', ['id' => 1], [
            'Folder' => ['name' => 'Renamed', 'path' => 'default'],
        ]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('Renamed', Folder::findOne(1)->name);
    }

    public function testUpdateOfAnUnknownFolderIsNotFound(): void
    {
        $this->login();

        $this->expectException(NotFoundHttpException::class);
        Yii::$app->runAction('admin/media/folder/update', ['id' => 99999]);
    }

    /**
     * A folder is deleted by typing its name, so a wrong answer leaves it alone.
     */
    public function testDeleteNeedsTheFoldersNameTyped(): void
    {
        $this->login();
        $folder = $this->createFolder('Archive', 'archive');

        $this->post('admin/media/folder/delete', ['id' => $folder->id], ['value' => 'Wrong']);

        self::assertNotNull(Folder::findOne($folder->id));
        self::assertNotEmpty(Yii::$app->getSession()->getFlash('danger'));

        $this->post('admin/media/folder/delete', ['id' => $folder->id], ['value' => 'Archive']);

        self::assertNull(Folder::findOne($folder->id));
        self::assertNotEmpty(Yii::$app->getSession()->getFlash('success'));
    }

    /**
     * Deleting a folder takes its files with it, which is what `Module::$enableDeleteNonEmptyFolders` gates — a
     * remote file system such as S3 turns it off.
     */
    public function testANonEmptyFolderIsDeletedWithItsFiles(): void
    {
        $this->login();

        self::assertGreaterThan(0, File::find()->where(['folder_id' => 1])->count());

        $this->post('admin/media/folder/delete', ['id' => 1], ['value' => 'Default']);

        self::assertNull(Folder::findOne(1));
        self::assertSame(0, (int)File::find()->where(['folder_id' => 1])->count());
    }

    public function testANonEmptyFolderIsKeptWhileTheModuleSaysSo(): void
    {
        $this->login();
        File::getModule()->enableDeleteNonEmptyFolders = false;

        $this->post('admin/media/folder/delete', ['id' => 1], ['value' => 'Default']);

        self::assertNotNull(Folder::findOne(1));
        self::assertGreaterThan(0, File::find()->where(['folder_id' => 1])->count());
    }

    public function testDeleteRefusesAGetRequest(): void
    {
        $this->login();

        $this->expectException(MethodNotAllowedHttpException::class);
        Yii::$app->runAction('admin/media/folder/delete', ['id' => 1]);
    }

    public function testOrderRewritesThePositions(): void
    {
        $this->login();

        $second = $this->createFolder('Archive', 'archive');

        $html = $this->post('admin/media/folder/order', [], [
            'folder' => [$second->id, 1],
        ]);

        self::assertIsString($html);
        self::assertLessThan(Folder::findOne(1)->position, Folder::findOne($second->id)->position);
    }

    private function createFolder(string $name, string $path): Folder
    {
        $folder = Folder::create();
        $folder->loadDefaultValues();
        $folder->name = $name;
        $folder->path = $path;

        self::assertTrue($folder->insert(), print_r($folder->getErrors(), true));

        return $folder;
    }

    private function post(string $route, array $params = [], array $bodyParams = []): mixed
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = Yii::$app->getRequest();
        $request->setBodyParams([...$bodyParams, $request->csrfParam => $request->getCsrfToken()]);

        return Yii::$app->runAction($route, $params);
    }

    private function login(): User
    {
        $user = $this->getUserFromFixture('admin');

        $permission = Yii::$app->getAuthManager()->getPermission(Folder::AUTH_FOLDER);
        Yii::$app->getAuthManager()->assign($permission, $user->id);

        Yii::$app->getUser()->setIdentity($user);

        return $user;
    }

    private function getUserFromFixture(string $key): User
    {
        /** @var UserFixture $fixture */
        $fixture = $this->getFixture('user');

        return User::findOne($fixture->data[$key]['id']);
    }
}
