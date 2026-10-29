<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Setup;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Setup\FolderSeeder;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Setup\Setup;
use Yii;

class FolderSeederTest extends TestCase
{
    public function testTheSeederIsRegistered(): void
    {
        self::assertSame(FolderSeeder::class, Setup::getComponent()->seeders['folders'] ?? null);
    }

    public function testWithoutADeclarationTheDefaultFolderIsInserted(): void
    {
        $seeder = new FolderSeeder();
        $seeder->run(null);

        $folder = $seeder->getInserted()[0] ?? self::fail('No folder inserted.');

        self::assertSame(Yii::t('media', 'FOLDER_DEFAULT_NAME'), $folder->getAttribute('name'));
        self::assertSame($folder->getPrimaryKey(), FolderCollection::getDefault()->id);
    }

    public function testTheDeclaredFoldersAreInsertedWhileThereAreNone(): void
    {
        $seeder = new FolderSeeder();
        $seeder->run([['name' => 'Bilder Å 画像 🖼'], ['name' => '']]);

        self::assertCount(1, $seeder->getInserted());
        self::assertCount(1, $seeder->getFailed());
        self::assertNotNull(Folder::findOne(['name' => 'Bilder Å 画像 🖼']));

        $seeder = new FolderSeeder();
        $seeder->run([['name' => 'Second']]);

        self::assertSame([], $seeder->getInserted());
    }
}
