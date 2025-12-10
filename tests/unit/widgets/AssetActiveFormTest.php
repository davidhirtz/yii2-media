<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\unit\widgets;

use Codeception\Test\Unit;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Traits\AssetFieldsTrait;
use Hirtz\Media\tests\data\Models\TestAsset;
use Hirtz\Skeleton\Codeception\Traits\AssetDirectoryTrait;
use Hirtz\Skeleton\Widgets\Forms\ActiveForm;
use Override;

class AssetActiveFormTest extends Unit
{
    use AssetDirectoryTrait;

    protected function _before(): void
    {
        $this->createAssetDirectory();
        parent::_before();
    }

    protected function _after(): void
    {
        $this->removeAssetDirectory();
        parent::_after();
    }

    public function testAssetFieldsTrait(): void
    {
        $file = File::create();
        $file->alt_text = 'Image Alt Text';
        $file->width = 100;
        $file->height = 100;
        $file->basename = 'vector';
        $file->extension = 'svg';

        $file->populateFolderRelation(FolderCollection::getDefault());

        $model = TestAsset::create();
        $model->populateFileRelation($file);

        $html = TestAssetActiveForm::make()
            ->model($model)
            ->render();

        $this->assertStringContainsString($model->getAttributeLabel('alt_text'), $html);
        $this->assertStringContainsString($file->alt_text, $html);
        $this->assertStringContainsString($file->getUrl(), $html);
    }
}

class TestAssetActiveForm extends ActiveForm
{
    use AssetFieldsTrait;

    #[Override]
    public function configure(): void
    {
        $this->rows = [
            $this->getPreview(),
            $this->getAltTextField(),
        ];

        parent::configure();
    }
}
