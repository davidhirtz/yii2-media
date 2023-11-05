<?php

namespace davidhirtz\yii2\media\migrations;

use davidhirtz\yii2\skeleton\db\traits\MigrationTrait;
use Yii;
use yii\db\Migration;

/**
* Class M200930092748Rbac
* @package davidhirtz\yii2\media\migrations
* @noinspection PhpUnused
*/
class M200930092748Rbac extends Migration
{
    use MigrationTrait;

    /**
     * @inheritDoc
     */
    public function safeUp()
    {
        $sourceLanguage = Yii::$app->sourceLanguage;
        $auth = Yii::$app->getAuthManager();

        $media = $auth->createRole('media');
        $auth->update('upload', $media);
        
        // File.
        $fileUpdate = $auth->createPermission('fileUpdate');
        $fileUpdate->description = Yii::t('media', 'Update files', [], $sourceLanguage);
        $auth->add($fileUpdate);

        $auth->addChild($media, $fileUpdate);

        $fileCreate = $auth->createPermission('fileCreate');
        $fileCreate->description = Yii::t('media', 'Upload or import new files', [], $sourceLanguage);
        $auth->add($fileCreate);

        $auth->addChild($fileCreate, $fileUpdate);
        $auth->addChild($media, $fileCreate);

        $fileDelete = $auth->createPermission('fileDelete');
        $fileDelete->description = Yii::t('media', 'Delete files', [], $sourceLanguage);
        $auth->add($fileDelete);

        $auth->addChild($fileDelete, $fileUpdate);
        $auth->addChild($media, $fileDelete);
        
        // Folder.
        $folderUpdate = $auth->createPermission('folderUpdate');
        $folderUpdate->description = Yii::t('media', 'Update folders', [], $sourceLanguage);
        $auth->add($folderUpdate);

        $auth->addChild($media, $folderUpdate);

        $folderCreate = $auth->createPermission('folderCreate');
        $folderCreate->description = Yii::t('media', 'Create new folders', [], $sourceLanguage);
        $auth->add($folderCreate);

        $auth->addChild($folderCreate, $folderUpdate);
        $auth->addChild($media, $folderCreate);

        $folderDelete = $auth->createPermission('folderDelete');
        $folderDelete->description = Yii::t('media', 'Delete folders', [], $sourceLanguage);
        $auth->add($folderDelete);

        $auth->addChild($folderDelete, $folderUpdate);
        $auth->addChild($media, $folderDelete);

        $folderOrder = $auth->createPermission('folderOrder');
        $folderOrder->description = Yii::t('media', 'Change folder order', [], $sourceLanguage);
        $auth->add($folderOrder);

        $auth->addChild($folderOrder, $folderUpdate);
        $auth->addChild($media, $folderOrder);
    }

    /**
     * @inheritDoc
     */
    public function safeDown()
    {
        $auth = Yii::$app->getAuthManager();

        $this->delete($auth->itemTable, ['name' => 'folderDelete']);
        $this->delete($auth->itemTable, ['name' => 'folderCreate']);
        $this->delete($auth->itemTable, ['name' => 'folderUpdate']);
        $this->delete($auth->itemTable, ['name' => 'folderOrder']);

        $this->delete($auth->itemTable, ['name' => 'fileDelete']);
        $this->delete($auth->itemTable, ['name' => 'fileCreate']);
        $this->delete($auth->itemTable, ['name' => 'fileUpdate']);

        $upload = $auth->createRole('upload');
        $auth->update('media', $upload);
    }
}