<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\Collections;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Models\Types\Type;
use Yii;

class FolderCollectionTest extends TestCase
{
    private const int TYPE_HERO = 2;

    public function testGetByTypeAnswersTheFoldersOfThatType(): void
    {
        Yii::$container->set(Folder::class, [
            'types' => fn (): array => [
                Type::make(Folder::TYPE_DEFAULT)->name('Default'),
                Type::make(self::TYPE_HERO)->name('Heroes'),
            ],
        ]);

        $default = FolderCollection::getDefault();
        $hero = $this->createFolder('Héroes Å 英雄 🦸', self::TYPE_HERO);

        $folders = FolderCollection::getByType(self::TYPE_HERO);

        self::assertSame([$hero->id], array_keys($folders));
        self::assertArrayHasKey($default->id, FolderCollection::getByType(Folder::TYPE_DEFAULT));
        self::assertSame([], FolderCollection::getByType(3));
    }

    private function createFolder(string $name, int $type): Folder
    {
        $folder = Folder::instantiateByType($type);
        $folder->type = $type;
        $folder->name = $name;
        $folder->path = 'heroes';

        self::assertTrue($folder->insert(), print_r($folder->getErrors(), true));

        return $folder;
    }
}
