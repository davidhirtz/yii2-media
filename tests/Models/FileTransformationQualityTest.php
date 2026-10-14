<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Test\Images\TestImageProcessor;
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

    public function testTheLabelNamesTheFormat(): void
    {
        $file = $this->createFile('pic');

        self::assertSame('WEBP transformation quality', $file->getAttributeLabel('transformation_quality_webp'));
        self::assertSame('80', $file->getCustomAttribute('transformation_quality_webp')?->getPlaceholder());
        self::assertSame('AVIF transformation quality', $file->getAttributeLabel('transformation_quality_avif'));
    }

    public function testOnlyTheFirstFormatIsShownByDefault(): void
    {
        $safe = $this->createFile('pic')->safeAttributes();

        self::assertContains('transformation_quality_webp', $safe);
        self::assertNotContains('transformation_quality_avif', $safe);
    }

    public function testTheModuleCanShowMoreFormats(): void
    {
        File::getModule()->transformationQualityExtensions = ['avif', 'webp', 'gif'];
        $safe = $this->createFile('pic')->safeAttributes();

        self::assertContains('transformation_quality_webp', $safe);
        self::assertContains('transformation_quality_avif', $safe);
    }

    public function testAFormatTheServerCannotEncodeIsNotShown(): void
    {
        $module = File::getModule();
        $module->imageProcessor = ['class' => TestImageProcessor::class, 'unencodable' => ['webp']];

        $safe = $this->createFile('pic')->safeAttributes();

        self::assertContains('transformation_quality_avif', $safe);
        self::assertNotContains('transformation_quality_webp', $safe);
    }

    public function testOnlyATransformableImageCanSetTheQuality(): void
    {
        $file = $this->buildFile('doc', 'pdf', 0, 0);
        self::assertNotContains('transformation_quality_webp', $file->safeAttributes());
    }

    #[DataProvider('provideInvalidQualities')]
    public function testTheQualityIsAnIntegerFromOneToOneHundred(mixed $quality): void
    {
        $file = $this->createFile('pic');
        $file->load(['File' => ['transformation_quality_webp' => $quality]]);

        self::assertFalse($file->validate());
        self::assertArrayHasKey('transformation_quality_webp', $file->getErrors());
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

        $file->load(['File' => ['transformation_quality_webp' => '60']]);

        $this->updateFile($file);
        self::assertSame('pic-webp60', $file->basename);
        self::assertSame(60, File::findOne($file->id)?->getAttribute('transformation_quality_webp'));
        self::assertFileExists($file->getFilePath());
        self::assertFileDoesNotExist($path);
        self::assertSame(0, $file->transformation_count);

        $file->load(['File' => ['transformation_quality_webp' => '70']]);
        $this->updateFile($file);
        self::assertSame('pic-webp70', $file->basename);

        $file->load(['File' => ['transformation_quality_webp' => '']]);
        $this->updateFile($file);
        self::assertSame('pic', $file->basename);
        self::assertNull($file->getAttribute('transformation_quality_webp'));
    }

    public function testEachFormatAddsItsOwnSuffix(): void
    {
        File::getModule()->transformationQualityExtensions = ['avif', 'webp'];

        $file = $this->createFile('pic');
        $file->load(['File' => ['transformation_quality_avif' => '40', 'transformation_quality_webp' => '70']]);
        $this->updateFile($file);
        self::assertSame('pic-webp70-avif40', $file->basename);

        $file->load(['File' => ['transformation_quality_webp' => '']]);
        $this->updateFile($file);
        self::assertSame('pic-avif40', $file->basename);
    }

    public function testACropKeepsTheQuality(): void
    {
        $file = $this->createFile('pic');
        $file->setAttribute('transformation_quality_webp', 60);
        $this->updateFile($file);

        $file->width = 50;
        $file->height = 50;
        $this->updateFile($file);
        self::assertSame('pic@50x50-webp60', $file->basename);

        $file->setAttribute('transformation_quality_webp', 40);
        $this->updateFile($file);
        self::assertSame('pic@50x50-webp40', $file->basename);
    }

    public function testAnUnchangedQualityKeepsTheName(): void
    {
        $file = $this->createFile('photo-webp60');
        $file->load(['File' => ['transformation_quality_webp' => '', 'name' => 'Renamed']]);

        $this->updateFile($file);
        self::assertSame('photo-webp60', $file->basename);
    }

    public function testEachQualityAppliesToItsOwnFormat(): void
    {
        $file = $this->createFile('pic');
        $file->setAttribute('transformation_quality_webp', 5);
        $file->setAttribute('transformation_quality_avif', 7);

        $transformation = File::getModule()->getTransformation('square');
        self::assertNotNull($transformation);

        $options = $transformation->getImageOptions();

        self::assertSame([...$options, 'webpQuality' => 5], $file->getTransformationImageOptions($transformation, 'webp'));
        self::assertSame([...$options, 'avifQuality' => 7], $file->getTransformationImageOptions($transformation, 'avif'));
        self::assertSame($options, $file->getTransformationImageOptions($transformation, 'jpg'));
    }

    /**
     * A file moved to a server writing another format keeps its value without applying it there.
     */
    public function testAQualityOfAFormatNoLongerShownIsKept(): void
    {
        $file = $this->createFile('pic');
        $file->setAttribute('transformation_quality_webp', 60);
        $this->updateFile($file);

        File::getModule()->imageProcessor = ['class' => TestImageProcessor::class, 'unencodable' => ['webp']];

        $file = File::findOne($file->id);
        self::assertNotNull($file);
        self::assertSame(60, $file->getAttribute('transformation_quality_webp'));

        $file->load(['File' => ['transformation_quality_avif' => '40']]);
        $this->updateFile($file);

        self::assertSame('pic-webp60-avif40', $file->basename);
        self::assertSame(60, File::findOne($file->id)?->getAttribute('transformation_quality_webp'));
    }

    public function testTheQualityIsWrittenToTheTransformation(): void
    {
        $default = $this->createTransformation($this->createNoisyFile('default'), 'webp');

        $file = $this->createNoisyFile('low');
        $file->setAttribute('transformation_quality_webp', 5);
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
