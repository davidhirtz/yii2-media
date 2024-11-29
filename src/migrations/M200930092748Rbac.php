<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\migrations;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\db\traits\MigrationTrait;
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
        $fileUpdate->description = Yii::t('media', 'Update files', [], $sourceLanguage);
        $auth->add($fileUpdate);

        $auth->addChild($media, $fileUpdate);

        $fileCreate = $auth->createPermission(File::AUTH_FILE_CREATE);
        $fileCreate->description = Yii::t('media', 'Upload or import new files', [], $sourceLanguage);
        $auth->add($fileCreate);

        $auth->addChild($fileCreate, $fileUpdate);
        $auth->addChild($media, $fileCreate);

        $fileDelete = $auth->createPermission(File::AUTH_FILE_DELETE);
        $fileDelete->description = Yii::t('media', 'Delete files', [], $sourceLanguage);
        $auth->add($fileDelete);

        $auth->addChild($fileDelete, $fileUpdate);
        $auth->addChild($media, $fileDelete);
        
        // Folder.
        $folderUpdate = $auth->createPermission(Folder::AUTH_FOLDER_UPDATE);
        $folderUpdate->description = Yii::t('media', 'Update folders', [], $sourceLanguage);
        $auth->add($folderUpdate);

        $auth->addChild($media, $folderUpdate);

        $folderCreate = $auth->createPermission(Folder::AUTH_FOLDER_CREATE);
        $folderCreate->description = Yii::t('media', 'Create new folders', [], $sourceLanguage);
        $auth->add($folderCreate);

        $auth->addChild($folderCreate, $folderUpdate);
        $auth->addChild($media, $folderCreate);

        $folderDelete = $auth->createPermission(Folder::AUTH_FOLDER_DELETE);
        $folderDelete->description = Yii::t('media', 'Delete folders', [], $sourceLanguage);
        $auth->add($folderDelete);

        $auth->addChild($folderDelete, $folderUpdate);
        $auth->addChild($media, $folderDelete);

        $folderOrder = $auth->createPermission(Folder::AUTH_FOLDER_ORDER);
        $folderOrder->description = Yii::t('media', 'Change folder order', [], $sourceLanguage);
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
