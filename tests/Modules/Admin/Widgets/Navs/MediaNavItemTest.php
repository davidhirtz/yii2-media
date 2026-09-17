<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Navs\MediaNavItem;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Test\Traits\UserFixtureTrait;
use Hirtz\Skeleton\Web\Controller;
use PHPUnit\Framework\Attributes\DataProvider;
use Yii;

/**
 * A file's assets and its transformations are tabs of the file page, so both keep the files item active.
 */
class MediaNavItemTest extends TestCase
{
    use UserFixtureTrait;

    /**
     * @return list<array{string}>
     */
    public static function fileRouteDataProvider(): array
    {
        return [
            ['admin/media/file/index'],
            ['admin/media/file/update'],
            ['admin/media/asset/index'],
            ['admin/media/transformation/index'],
        ];
    }

    #[DataProvider('fileRouteDataProvider')]
    public function testTheFilesItemIsActive(string $route): void
    {
        self::assertStringContainsString(
            '<a class="nav-link active" href="/admin/media/file/index">',
            $this->render($route),
        );
    }

    public function testTheFoldersItemIsActiveOnItsOwnRoutes(): void
    {
        $html = $this->render('admin/media/folder/update');

        self::assertStringContainsString('<a class="nav-link active" href="/admin/media/folder/index">', $html);
        self::assertStringNotContainsString('<a class="nav-link active" href="/admin/media/file/index">', $html);
    }

    public function testNeitherItemIsActiveOnAnUnrelatedRoute(): void
    {
        self::assertStringNotContainsString('nav-link active', $this->render('admin/user/index'));
    }

    private function render(string $route): string
    {
        $user = $this->getUserFromFixture('admin');

        $this->assignPermission($user->id, File::AUTH_FILE);
        $this->assignPermission($user->id, Folder::AUTH_FOLDER);
        $this->getWebUser()->setIdentity($user);

        Yii::$app->controller = new class ($route, Yii::$app) extends Controller {
            public function getRoute(): string
            {
                return $this->id;
            }
        };

        return MediaNavItem::make()->render();
    }
}
