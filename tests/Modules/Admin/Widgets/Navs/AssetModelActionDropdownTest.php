<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\AssetGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetModelActionDropdown;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetSubmenuItem;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Test\Traits\UserFixtureTrait;
use Override;
use Yii;

class AssetModelActionDropdownTest extends TestCase
{
    use UserFixtureTrait;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->getUserFromFixture('admin');
        $this->assignPermission($user->id, File::AUTH_FILE);
        $this->getWebUser()->setIdentity($user);
    }

    /**
     * The upload swaps its target with the element of the same id in the response, so the button must name the asset
     * grid that is on the page, not the file grid of the create route.
     */
    public function testFileUploadTargetsRenderedAssetGrid(): void
    {
        $provider = $this->getProvider();

        $dropdown = (string)AssetModelActionDropdown::make()
            ->provider($provider);

        self::assertStringContainsString('data-target="#' . AssetGridView::ID . '"', $dropdown);

        $grid = (string)AssetGridView::make()
            ->provider($provider);

        self::assertStringContainsString('id="' . AssetGridView::ID . '"', $grid);
    }

    /**
     * The asset count sits outside the target, so the upload has to name it as an out-of-band swap.
     */
    public function testFileUploadRefreshesTheAssetCountOutOfBand(): void
    {
        $dropdown = (string)AssetModelActionDropdown::make()
            ->provider($this->getProvider());

        self::assertStringContainsString('data-select-oob="#' . AssetSubmenuItem::ID . '"', $dropdown);

        $item = (string)AssetSubmenuItem::make()
            ->badge(3)
            ->label('Assets')
            ->url(['/admin/media/file/index']);

        self::assertStringContainsString('id="' . AssetSubmenuItem::ID . '"', $item);
    }

    public function testTheFileButtonsNeedTheFilePermission(): void
    {
        $this->getWebUser()->setIdentity($this->getUserFromFixture('disabled'));

        $dropdown = (string)AssetModelActionDropdown::make()
            ->provider($this->getProvider());

        self::assertStringNotContainsString('data-target="#' . AssetGridView::ID . '"', $dropdown);
        self::assertStringContainsString('/admin/media/asset/create?testassetmodel=1', $dropdown);
    }

    protected function getProvider(): AssetArrayDataProvider
    {
        $model = TestAssetModel::create();
        $model->id = 1;

        return Yii::$container->get(AssetArrayDataProvider::class, config: [
            'model' => $model,
        ]);
    }
}
