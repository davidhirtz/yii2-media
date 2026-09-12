<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use Yii;
use yii\db\Migration;

/**
 * @noinspection PhpUnused
 */
class M200930092748Rbac extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $sourceLanguage = Yii::$app->sourceLanguage;
        $auth = Yii::$app->getAuthManager();

        $media = $auth->createRole('media');
        $auth->update('upload', $media);

        // File.
        $fileUpdate = $auth->createPermission(File::AUTH_FILE_UPDATE);
        $fileUpdate->description = Yii::t('media', 'AUTH_FILE_UPDATE_DESCRIPTION', [], $sourceLanguage);
        $auth->add($fileUpdate);

        $auth->addChild($media, $fileUpdate);

        $fileCreate = $auth->createPermission(File::AUTH_FILE_CREATE);
        $fileCreate->description = Yii::t('media', 'AUTH_FILE_CREATE_DESCRIPTION', [], $sourceLanguage);
        $auth->add($fileCreate);

        $auth->addChild($fileCreate, $fileUpdate);
        $auth->addChild($media, $fileCreate);

        $fileDelete = $auth->createPermission(File::AUTH_FILE_DELETE);
        $fileDelete->description = Yii::t('media', 'AUTH_FILE_DELETE_DESCRIPTION', [], $sourceLanguage);
        $auth->add($fileDelete);

        $auth->addChild($fileDelete, $fileUpdate);
        $auth->addChild($media, $fileDelete);

        // Folder.
        $folderUpdate = $auth->createPermission(Folder::AUTH_FOLDER_UPDATE);
        $folderUpdate->description = Yii::t('media', 'AUTH_FOLDER_UPDATE_DESCRIPTION', [], $sourceLanguage);
        $auth->add($folderUpdate);

        $auth->addChild($media, $folderUpdate);

        $folderCreate = $auth->createPermission(Folder::AUTH_FOLDER_CREATE);
        $folderCreate->description = Yii::t('media', 'AUTH_FOLDER_CREATE_DESCRIPTION', [], $sourceLanguage);
        $auth->add($folderCreate);

        $auth->addChild($folderCreate, $folderUpdate);
        $auth->addChild($media, $folderCreate);

        $folderDelete = $auth->createPermission(Folder::AUTH_FOLDER_DELETE);
        $folderDelete->description = Yii::t('media', 'AUTH_FOLDER_DELETE_DESCRIPTION', [], $sourceLanguage);
        $auth->add($folderDelete);

        $auth->addChild($folderDelete, $folderUpdate);
        $auth->addChild($media, $folderDelete);

        $folderOrder = $auth->createPermission(Folder::AUTH_FOLDER_ORDER);
        $folderOrder->description = Yii::t('media', 'AUTH_FOLDER_ORDER_DESCRIPTION', [], $sourceLanguage);
        $auth->add($folderOrder);

        $auth->addChild($folderOrder, $folderUpdate);
        $auth->addChild($media, $folderOrder);
    }

    public function safeDown(): void
    {
        $auth = Yii::$app->getAuthManager();

        $this->delete($auth->itemTable, ['name' => Folder::AUTH_FOLDER_DELETE]);
        $this->delete($auth->itemTable, ['name' => Folder::AUTH_FOLDER_CREATE]);
        $this->delete($auth->itemTable, ['name' => Folder::AUTH_FOLDER_ORDER]);
        $this->delete($auth->itemTable, ['name' => Folder::AUTH_FOLDER_UPDATE]);

        $this->delete($auth->itemTable, ['name' => File::AUTH_FILE_DELETE]);
        $this->delete($auth->itemTable, ['name' => File::AUTH_FILE_CREATE]);
        $this->delete($auth->itemTable, ['name' => File::AUTH_FILE_UPDATE]);

        $upload = $auth->createRole('upload');
        $auth->update('media', $upload);
    }
}
