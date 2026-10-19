<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Modules\Admin\Widgets\Grids\FileAssetGridView;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Models\User;
use Hirtz\Media\Models\File;
use Override;
use Yii;
use yii\data\ArrayDataProvider;
use yii\web\Controller;

class FileAssetGridViewTest extends TestCase
{
    use MediaFixtureTrait;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function fixtures(): array
    {
        return [
            'folder' => FolderFixture::class,
            'file' => FileFixture::class,
            'user' => UserFixture::class,
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->controller = new Controller('test', Yii::$app);
    }

    public function testTheAssetsAreRemovedBySelection(): void
    {
        $user = User::find()->limit(1)->one() ?? self::fail('No user.');
        Yii::$app->getAuthManager()->assign(Yii::$app->getAuthManager()->getPermission(File::AUTH_FILE), $user->id);
        $this->getWebUser()->setIdentity($user);

        $file = $this->getFileFromFixture('file-1');

        $html = (string)TestFileAssetGridView::make()
            ->file($file)
            ->provider(new ArrayDataProvider(['allModels' => [$this->createAsset($file)], 'key' => 'id']));

        self::assertStringContainsString('name="selection[]" value="1"', $html);
        self::assertStringContainsString(Html::encode(Url::to(['/admin/media/asset/delete-all', 'file' => $file->id])), $html);
        self::assertStringNotContainsString('asset/delete?', $html);
    }

    public function testNoSelectionWithoutThePermission(): void
    {
        $file = $this->getFileFromFixture('file-1');

        $html = (string)TestFileAssetGridView::make()
            ->file($file)
            ->provider(new ArrayDataProvider(['allModels' => [$this->createAsset($file)], 'key' => 'id']));

        self::assertStringNotContainsString('data-check', $html);
        self::assertStringNotContainsString('delete-all', $html);
    }

    private function createAsset(File $file): TestAsset
    {
        $model = TestAssetModel::create();
        $model->id = 1;

        $asset = TestAsset::create();
        $asset->setAttributes(['id' => 1, 'status' => TestAsset::STATUS_ENABLED], false);
        $asset->populateModelRelation($model);
        $asset->populateFileRelation($file);

        return $asset;
    }
}

/**
 * `TestAssetModel` has no table to read an admin name from.
 */
class TestFileAssetGridView extends FileAssetGridView
{
    #[Override]
    protected function getModelColumn(): ?Column
    {
        return null;
    }
}
