<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use Hirtz\Skeleton\I18n\Message;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M260914120000AuthItems extends Migration
{
    use MigrationTrait;

    private const string ROLE_MEDIA = 'media';

    private const array LEGACY_FILE = ['fileCreate', 'fileDelete', 'fileUpdate'];
    private const array LEGACY_FOLDER = ['folderCreate', 'folderDelete', 'folderOrder', 'folderUpdate'];

    public function safeUp(): void
    {
        $this->addPermission(File::AUTH_FILE, $this->getFileDescription(), self::ROLE_MEDIA);
        $this->replaceAuthItems(self::LEGACY_FILE, File::AUTH_FILE);

        $this->addPermission(Folder::AUTH_FOLDER, $this->getFolderDescription(), self::ROLE_MEDIA);
        $this->replaceAuthItems(self::LEGACY_FOLDER, Folder::AUTH_FOLDER);
    }

    public function safeDown(): void
    {
        $this->restoreAuthItems(self::LEGACY_FOLDER, Folder::AUTH_FOLDER, $this->getFolderDescription());
        $this->restoreAuthItems(self::LEGACY_FILE, File::AUTH_FILE, $this->getFileDescription());
    }

    private function getFileDescription(): Message
    {
        return Message::make('media', 'AUTH_FILE_DESCRIPTION');
    }

    private function getFolderDescription(): Message
    {
        return Message::make('media', 'AUTH_FOLDER_DESCRIPTION');
    }
}
