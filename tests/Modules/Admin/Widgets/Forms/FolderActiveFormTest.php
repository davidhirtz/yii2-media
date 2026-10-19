<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Forms;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Forms\FolderActiveForm;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Models\Types\Type;
use Yii;

class FolderActiveFormTest extends TestCase
{
    public function testASingleTypeIsNotOffered(): void
    {
        $html = (string)FolderActiveForm::make()
            ->model(Folder::create());

        self::assertStringNotContainsString('<select id="folder-type"', $html);
    }

    public function testSeveralTypesAreOfferedAndReloadTheForm(): void
    {
        Yii::$container->set(Folder::class, [
            'types' => fn (): array => [
                Type::make(Folder::TYPE_DEFAULT)->name('Default'),
                Type::make(2)->name('Heroes'),
            ],
        ]);

        $html = (string)FolderActiveForm::make()
            ->model(Folder::create());

        self::assertStringContainsString('<select id="folder-type"', $html);
        self::assertStringContainsString('Heroes', $html);
    }
}
