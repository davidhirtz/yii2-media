<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Forms\Traits;

use Hirtz\Media\Modules\Admin\Widgets\Forms\Traits\AssetFieldsTrait;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\FileFixtureTrait;
use Hirtz\Skeleton\Widgets\Forms\ActiveForm;
use Override;

class AssetFieldsTraitTest extends TestCase
{
    use FileFixtureTrait;

    public function testAssetFields(): void
    {
        $file = $this->getFileFromFixture('file-1');

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
