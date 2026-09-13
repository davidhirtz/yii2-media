<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\Forms;

use Hirtz\Media\Models\Forms\TransformationForm;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Transformation;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Override;
use Yii;

/**
 * The form behind the public transformation endpoint: everything a visitor's URL is allowed to say ends up here,
 * so it is the one place that decides which folder, file and transformation a request may reach.
 */
class TransformationFormTest extends TestCase
{
    use MediaFixtureTrait;

    #[Override]
    public function fixtures(): array
    {
        return [
            'folder' => FolderFixture::class,
            'file' => FileFixture::class,
        ];
    }

    public function testAValidPathResolvesTheFileAndTheFolder(): void
    {
        $form = $this->createForm('default/admin/test-1.jpg');

        self::assertTrue($form->validate(), print_r($form->getErrors(), true));

        self::assertSame('default', $form->folderPath);
        self::assertSame(Transformation::NAME_ADMIN, $form->transformationName);
        self::assertSame('test-1', $form->basename);
        self::assertSame('jpg', $form->extension);

        self::assertSame(1, $form->folder?->id);
        self::assertSame(1, $form->file?->id);
    }

    /**
     * A transformation extension is the output format, so the file behind it is looked up without one.
     */
    public function testATransformationExtensionFindsTheOriginalFile(): void
    {
        $form = $this->createForm('default/admin/test-1.avif');

        self::assertTrue($form->validate(), print_r($form->getErrors(), true));

        self::assertSame('avif', $form->extension);
        self::assertSame(1, $form->file?->id);
    }

    public function testAnUnknownTransformationIsRefused(): void
    {
        $form = $this->createForm('default/does-not-exist/test-1.jpg');

        self::assertFalse($form->validate());
        self::assertArrayHasKey('transformationName', $form->getErrors());
    }

    public function testAnExtensionThatIsNotAllowedIsRefused(): void
    {
        $form = $this->createForm('default/admin/test-1.php');

        self::assertFalse($form->validate());
        self::assertArrayHasKey('extension', $form->getErrors());
    }

    /**
     * Both the folder and the transformation are a single path segment each, so nothing a URL says can climb out of
     * the upload directory.
     */
    public function testAPathCannotEscapeTheUploadDirectory(): void
    {
        $paths = [
            '../../etc/admin/test-1.jpg',
            'default/../admin/test-1.jpg',
            'default/admin/../../../etc/passwd.jpg',
        ];

        foreach ($paths as $path) {
            $form = $this->createForm($path);

            self::assertFalse($form->validate(), "Accepted $path");
            self::assertNull($form->file);
        }
    }

    public function testAFolderThatDoesNotExistIsRefused(): void
    {
        $form = $this->createForm('nowhere/admin/test-1.jpg');

        self::assertFalse($form->validate());
        self::assertArrayHasKey('folderPath', $form->getErrors());
    }

    public function testAFileThatDoesNotExistIsRefused(): void
    {
        $form = $this->createForm('default/admin/nowhere.jpg');

        self::assertFalse($form->validate());
        self::assertArrayHasKey('file', $form->getErrors());
    }

    public function testAFileOfAnotherFolderIsNotReached(): void
    {
        $folder = $this->createFolder('other');

        $form = $this->createForm("$folder->path/admin/test-1.jpg");

        self::assertFalse($form->validate());
        self::assertArrayHasKey('file', $form->getErrors());
    }

    public function testAPathWithoutAFilenameIsRefused(): void
    {
        $form = $this->createForm('default/admin/');

        self::assertFalse($form->validate());
        self::assertArrayHasKey('basename', $form->getErrors());
    }

    public function testABasenameOverTheLimitIsRefused(): void
    {
        $form = $this->createForm('default/admin/' . str_repeat('a', 256) . '.jpg');

        self::assertFalse($form->validate());
        self::assertArrayHasKey('basename', $form->getErrors());
    }

    public function testTheTransformationIsBuiltFromTheResolvedRecords(): void
    {
        $form = $this->createForm('default/admin/test-1.avif');

        self::assertTrue($form->validate());

        $transformation = $form->transformation;

        self::assertSame(Transformation::NAME_ADMIN, $transformation->name);
        self::assertSame('avif', $transformation->extension);
        self::assertSame($form->file->id, $transformation->file_id);
        self::assertSame($form->folder->id, $transformation->file->folder_id);
    }

    private function createFolder(string $path): Folder
    {
        $folder = Folder::create();
        $folder->loadDefaultValues();
        $folder->name = ucfirst($path);
        $folder->path = $path;

        self::assertTrue($folder->insert(), print_r($folder->getErrors(), true));

        return $folder;
    }

    private function createForm(string $path): TransformationForm
    {
        $form = TransformationForm::create();
        $form->path = $path;

        return $form;
    }
}
