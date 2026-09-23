<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Transformations;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Transformations\Transformation;
use PHPUnit\Framework\Attributes\DataProvider;
use yii\base\InvalidConfigException;

class TransformationTest extends TestCase
{
    /**
     * @return array<string, array{string, int|null, int|null}>
     */
    public static function selfDescribingNameProvider(): array
    {
        return [
            'width' => ['w_200', 200, null],
            'height' => ['h_200', null, 200],
            'width with modifier' => ['w_300@2', 600, null],
            'height with modifier' => ['h_300@2', null, 600],
            'fractional modifier' => ['w_400@1.5', 600, null],
            'modifier below one' => ['w_500@.5', 250, null],
            'both dimensions' => ['w_200,h_300', 200, 300],
            'both dimensions with modifier' => ['w_200,h_300@2', 400, 600],
        ];
    }

    #[DataProvider('selfDescribingNameProvider')]
    public function testASelfDescribingNameCarriesItsDimensions(string $name, ?int $width, ?int $height): void
    {
        $transformation = Transformation::fromName($name);

        self::assertInstanceOf(Transformation::class, $transformation);
        self::assertSame($name, $transformation->name);
        self::assertSame($width, $transformation->getWidth());
        self::assertSame($height, $transformation->getHeight());
    }

    public function testANameThatDescribesNothingIsNotParsed(): void
    {
        self::assertNull(Transformation::fromName('xs'));
        self::assertNull(Transformation::fromName('w_'));
        self::assertNull(Transformation::fromName('x_200'));
    }

    public function testANamelessTransformationThrows(): void
    {
        $this->expectException(InvalidConfigException::class);
        Transformation::make('');
    }

    public function testATransformationLargerThanTheFileIsNotApplicable(): void
    {
        $file = $this->createFileModel(200, 100);

        self::assertTrue(Transformation::make('a')->width(200)->height(100)->isApplicableTo($file));
        self::assertFalse(Transformation::make('a')->width(300)->isApplicableTo($file));
        self::assertFalse(Transformation::make('a')->height(200)->isApplicableTo($file));
    }

    public function testAKeptAspectRatioOnlyNeedsOneValidDimension(): void
    {
        $file = $this->createFileModel(200, 100);
        $transformation = Transformation::make('a')->width(100)->height(300)->keepAspectRatio();

        self::assertTrue($transformation->isApplicableTo($file));
        self::assertFalse(Transformation::make('a')->width(100)->height(300)->isApplicableTo($file));
    }

    public function testAScalingTransformationIsAlwaysApplicable(): void
    {
        $file = $this->createFileModel(200, 100);
        self::assertTrue(Transformation::make('a')->width(5000)->scaleUp()->isApplicableTo($file));
    }

    public function testANonImageIsNeverApplicable(): void
    {
        $file = File::create();
        $file->extension = 'pdf';

        self::assertFalse(Transformation::make('a')->width(10)->isApplicableTo($file));
    }

    public function testTheWidthIsDerivedFromTheHeightWhenNoneIsSet(): void
    {
        $file = $this->createFileModel(200, 100);

        self::assertSame(150, Transformation::make('a')->width(150)->getWidthFor($file));
        self::assertSame(100, Transformation::make('a')->height(50)->getWidthFor($file));
        self::assertSame(200, Transformation::make('a')->getWidthFor($file));
    }

    public function testTheSizeFollowsTheResize(): void
    {
        $file = $this->createFileModel(1600, 1200);

        self::assertSame([800, 600], Transformation::make('a')->width(800)->getSizeFor($file));
        self::assertSame([400, 300], Transformation::make('a')->height(300)->getSizeFor($file));
        self::assertSame([300, 300], Transformation::make('a')->width(300)->height(300)->getSizeFor($file));
        self::assertSame([840, 630], Transformation::make('a')->width(1200)->height(630)->keepAspectRatio()->getSizeFor($file));
        self::assertSame([1600, 1200], Transformation::make('a')->width(2000)->getSizeFor($file));
        self::assertSame([2000, 1500], Transformation::make('a')->width(2000)->scaleUp()->getSizeFor($file));
    }

    private function createFileModel(int $width, int $height): File
    {
        $file = File::create();
        $file->extension = 'jpg';
        $file->width = $width;
        $file->height = $height;

        return $file;
    }
}
