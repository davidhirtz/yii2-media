<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Assets;

use Hirtz\Media\Assets\PdfPreviewAssetBundle;
use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Widgets\Buttons\FileUploadButton;

class PdfPreviewAssetBundleTest extends TestCase
{
    public function testTheUploadButtonLoadsTheScriptWhenPdfsAreAllowed(): void
    {
        File::getModule()->allowedExtensions = ['jpg', 'pdf'];

        $this->renderUploadButton();

        self::assertArrayHasKey(PdfPreviewAssetBundle::class, Application::current()->getView()->assetBundles);
    }

    public function testTheUploadButtonLoadsNoScriptWhenPdfsAreRefused(): void
    {
        File::getModule()->allowedExtensions = ['jpg'];

        $this->renderUploadButton();

        self::assertArrayNotHasKey(PdfPreviewAssetBundle::class, Application::current()->getView()->assetBundles);
    }

    private function renderUploadButton(): void
    {
        FileUploadButton::make()
            ->url(['/admin/media/file/create'])
            ->render();
    }
}
