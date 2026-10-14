<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Helpers\FileHelper;
use Imagick;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

class FileTransformationQualityTest extends TestCase
{
    use MediaFileTrait;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $module = File::getModule();
        $module->transformationExtensions = ['webp', 'avif'];
        $module->addTransformation(Transformation::make('square')->width(50)->height(50));

        $this->folder = $this->createFolder('Uploads', 'uploads');
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testTheLabelNamesTheFormatServedFirst(): void
    {
        $file = $this->createFile('pic');

        self::assertSame('WEBP quality', $file->getAttributeLabel('transformation_quality'));
        self::assertSame('80', $file->getCustomAttribute('transformation_quality')?->getPlaceholder());
    }

    public function testOnlyATransformableImageCanSetTheQuality(): void
    {
        self::assertContains('transformation_quality', $this->createFile('pic')->safeAttributes());

        $file = $this->buildFile('doc', 'pdf', 0, 0);
        self::assertNotContains('transformation_quality', $file->safeAttributes());
    }

    #[DataProvider('provideInvalidQualities')]
    public function testTheQualityIsAnIntegerFromOneToOneHundred(mixed $quality): void
    {
        $file = $this->createFile('pic');
        $file->load(['File' => ['transformation_quality' => $quality]]);

        self::assertFalse($file->validate());
        self::assertArrayHasKey('transformation_quality', $file->getErrors());
        self::assertSame('pic', $file->basename);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function provideInvalidQualities(): array
    {
        return [
            'zero' => ['0'],
            'above' => ['101'],
            'fraction' => ['50.5'],
            'text' => ['high'],
        ];
    }

    public function testAQualityNamesTheFileApartAndDropsItsTransformations(): void
    {
        $file = $this->createFile('pic');
        $transformation = $this->createTransformation($file, 'webp');
        $path = $transformation->getFilePath();

        $file->load(['File' => ['transformation_quality' => '60']]);

        $this->updateFile($file);
        self::assertSame('pic-q60', $file->basename);
        self::assertSame(60, File::findOne($file->id)?->transformation_quality);
        self::assertFileExists($file->getFilePath());
        self::assertFileDoesNotExist($path);
        self::assertSame(0, $file->transformation_count);

        $file->load(['File' => ['transformation_quality' => '70']]);
        $this->updateFile($file);
        self::assertSame('pic-q70', $file->basename);

        $file->load(['File' => ['transformation_quality' => '']]);
        $this->updateFile($file);
        self::assertSame('pic', $file->basename);
        self::assertNull($file->transformation_quality);
    }

    public function testACropKeepsTheQuality(): void
    {
        $file = $this->createFile('pic');
        $file->transformation_quality = 60;
        $this->updateFile($file);

        $file->width = 50;
        $file->height = 50;
        $this->updateFile($file);
        self::assertSame('pic@50x50-q60', $file->basename);

        $file->transformation_quality = 40;
        $this->updateFile($file);
        self::assertSame('pic@50x50-q40', $file->basename);
    }

    public function testAnUnchangedQualityKeepsTheName(): void
    {
        $file = $this->createFile('photo-q60');
        $file->load(['File' => ['transformation_quality' => '', 'name' => 'Renamed']]);

        $this->updateFile($file);
        self::assertSame('photo-q60', $file->basename);
    }

    public function testTheQualityAppliesToTheFormatServedFirstOnly(): void
    {
        $file = $this->createFile('pic');
        $file->transformation_quality = 5;

        $transformation = File::getModule()->getTransformation('square');
        self::assertNotNull($transformation);

        self::assertSame(5, $file->getTransformationImageOptions($transformation, 'webp')['webpQuality']);
        self::assertSame($transformation->getImageOptions(), $file->getTransformationImageOptions($transformation, 'avif'));
        self::assertSame($transformation->getImageOptions(), $file->getTransformationImageOptions($transformation, 'jpg'));
    }

    public function testTheQualityIsWrittenToTheTransformation(): void
    {
        $default = $this->createTransformation($this->createNoisyFile('default'), 'webp');

        $file = $this->createNoisyFile('low');
        $file->transformation_quality = 5;
        $this->updateFile($file);

        $low = $this->createTransformation($file, 'webp');

        self::assertLessThan($default->size, $low->size);
    }

    private function updateFile(File $file): void
    {
        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
    }

    /**
     * A plain placeholder encodes to the same few bytes at any quality.
     */
    private function createNoisyFile(string $basename): File
    {
        $file = $this->createFile($basename);

        $image = new Imagick();
        $image->newPseudoImage(100, 100, 'plasma:');
        $image->writeImage('jpeg:' . $file->getFilePath());

        return $file;
    }

    private function createTransformation(File $file, string $extension): FileTransformation
    {
        $transformation = FileTransformation::create();
        $transformation->name = 'square';
        $transformation->extension = $extension;
        $transformation->populateFileRelation($file);

        self::assertTrue($transformation->insert());

        return $transformation;
    }
}
