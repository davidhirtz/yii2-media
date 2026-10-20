<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Forms;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Widgets\Forms\FileActiveForm;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Models\CustomAttributes\TextCustomAttribute;

class FileActiveFormTest extends TestCase
{
    use MediaFixtureTrait;

    public function testTheBasenameFieldEditsTheNameBesideItsHashDirectory(): void
    {
        $file = $this->getFileFromFixture('file-1');
        $file->basename = '3f9a2c1b/photo';

        $html = (string)FileActiveForm::make()
            ->model($file);

        self::assertStringContainsString('3f9a2c1b/', $html);
        self::assertStringContainsString('name="File[basename]" value="photo"', $html);
    }

    public function testABasenameWithoutHashDirectoryIsEditedWhole(): void
    {
        $file = $this->getFileFromFixture('file-1');
        $file->basename = 'subfolder/photo';

        $html = (string)FileActiveForm::make()
            ->model($file);

        self::assertStringContainsString('name="File[basename]" value="subfolder/photo"', $html);
    }

    public function testAPlainTextCustomAttributeIsShownBesideTheSize(): void
    {
        File::getModule()->fileCustomAttributes = [
            fn (): array => [
                TextCustomAttribute::make('credit'),
                TextCustomAttribute::make('checksum')->plainText(),
            ],
        ];

        $file = $this->getFileFromFixture('file-1');
        $file->size = 2048;
        $file->setAttribute('checksum', 'Å9f');

        $html = (string)FileActiveForm::make()->model($file);

        self::assertStringContainsString('id="file-size-row"', $this->findFieldset($html, '<div class="form-text">Å9f</div>'));
        self::assertStringContainsString('id="file-alt-text"', $this->findFieldset($html, 'id="file-credit"'));
    }

    private function findFieldset(string $html, string $needle): string
    {
        preg_match_all('#<fieldset.*?</fieldset>#s', $html, $matches);

        foreach ($matches[0] as $fieldset) {
            if (str_contains($fieldset, $needle)) {
                return $fieldset;
            }
        }

        self::fail("No fieldset holds $needle.");
    }
}
