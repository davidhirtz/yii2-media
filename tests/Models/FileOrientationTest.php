<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Helpers\ImageSize;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Db\DateTime;
use Hirtz\Skeleton\Filters\PageCache;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;
use Yii;
use yii\caching\TagDependency;

/**
 * A phone stores a portrait photo as landscape pixels plus an EXIF orientation. What the record says, what a browser
 * shows and what every transformation writes has to be the upright image.
 */
class FileOrientationTest extends TestCase
{
    use MediaFileTrait;

    private string $source;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->logger->isRecording = true;

        File::getModule()->addTransformation(Transformation::make('small')->width(50)->keepAspectRatio());

        $this->folder = $this->createFolder('Uploads', 'uploads');

        $this->source = Yii::getAlias('@runtime/file-orientation-test/portrait.jpg');
        $this->writeImage($this->source, 200, 100, 6);
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory(dirname($this->source));
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);

        parent::tearDown();
    }

    public function testImagesAreTurnedUprightByDefault(): void
    {
        self::assertTrue(File::getModule()->autorotateImages);
    }

    public function testAnUploadIsTurnedUpright(): void
    {
        $file = $this->upload();

        self::assertSame([100, 200], [$file->width, $file->height]);
        self::assertSame(1, ImageSize::getOrientation($file->getFilePath()));
        self::assertSame([100, 200], array_slice((array)getimagesize($file->getFilePath()), 0, 2));
    }

    public function testAnUploadKeptSidewaysAnswersTheSizeItIsDisplayedAt(): void
    {
        $file = $this->upload(autorotate: false);

        self::assertSame(6, ImageSize::getOrientation($file->getFilePath()));
        self::assertSame([100, 200], [$file->width, $file->height]);

        $transformation = FileTransformation::create();
        $transformation->name = 'small';
        $transformation->populateFileRelation($file);

        self::assertTrue($transformation->insert(), print_r($this->logger->messages, true));

        self::assertSame([50, 100], [$transformation->width, $transformation->height]);
        self::assertSame([50, 100], File::getModule()->getTransformation('small')?->getSizeFor($file));
    }

    public function testASidewaysFileIsOrientedOnce(): void
    {
        $file = $this->createFile('legacy', width: 200, height: 100);
        $this->writeImage($file->getFilePath(), 200, 100, 6);

        $transformation = FileTransformation::create();
        $transformation->name = 'small';
        $transformation->populateFileRelation($file);
        self::assertTrue($transformation->insert(), print_r($this->logger->messages, true));

        self::assertTrue($file->orientImage());

        self::assertSame([100, 200], [$file->width, $file->height]);
        self::assertSame(0, File::findOne($file->id)?->transformation_count);
        self::assertFileDoesNotExist($transformation->getFilePath());

        self::assertFalse($file->orientImage());
    }

    /**
     * @see https://github.com/davidhirtz/yii2-monorepo/issues/391
     */
    public function testOrientingAFileMovesItsVersionAndInvalidatesThePageCache(): void
    {
        $file = $this->createFile('legacy', width: 200, height: 100);
        $this->writeImage($file->getFilePath(), 200, 100, 6);

        $file->updateAttributes(['updated_at' => new DateTime('2020-01-01 00:00:00')]);
        $url = $file->getUrlWithVersion();

        $cache = Yii::$app->getCache();
        self::assertNotNull($cache);

        $dependency = new TagDependency(['tags' => [PageCache::TAG_DEPENDENCY_KEY]]);
        $cache->set('file-orientation-test', 'page', 0, $dependency);

        self::assertTrue($file->orientImage());

        self::assertNotSame($url, $file->getUrlWithVersion());
        self::assertNotSame($url, File::findOne($file->id)?->getUrlWithVersion());
        self::assertFalse($cache->get('file-orientation-test'));
    }

    private function upload(bool $autorotate = true): File
    {
        $file = File::create();
        $file->loadDefaultValues();
        $file->autorotateImages = $autorotate;
        $file->populateFolderRelation($this->folder);
        self::assertTrue($file->copy($this->source));

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        return $file;
    }
}
