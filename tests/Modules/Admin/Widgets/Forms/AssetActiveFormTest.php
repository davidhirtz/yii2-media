<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Forms;

use Hirtz\Media\Modules\Admin\Widgets\Forms\AssetActiveForm;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;

class AssetActiveFormTest extends TestCase
{
    use MediaFixtureTrait;

    public function testTheFormRendersThePreviewAndTheDefaultCustomAttributes(): void
    {
        $file = $this->getFileFromFixture('file-1');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $html = (string)AssetActiveForm::make()
            ->model($asset);

        self::assertStringContainsString($file->getUrl(), $html);
        self::assertStringContainsString($asset->getAttributeLabel('name'), $html);
        self::assertStringContainsString($asset->getAttributeLabel('alt_text'), $html);
    }

    /**
     * The alt text of the file is the placeholder, so an empty asset shows what the site would fall back to.
     */
    public function testTheAltTextFieldFallsBackToTheFile(): void
    {
        $file = $this->getFileFromFixture('file-1');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $html = (string)AssetActiveForm::make()
            ->model($asset);

        self::assertStringContainsString($file->alt_text, $html);
    }
}
