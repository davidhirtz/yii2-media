<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Forms;

use Hirtz\Media\Modules\Admin\Widgets\Forms\FileActiveForm;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;

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
}
