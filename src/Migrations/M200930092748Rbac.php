<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use Yii;
use yii\db\Migration;

/**
 * The permission names and descriptions this creates are hardcoded: `M2609141[0-6]0000AuthItems` collapses them
 * into one permission per model, so neither the constants nor the message keys exist any more.
 *
 * @noinspection PhpUnused
 */
class M200930092748Rbac extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $auth = Yii::$app->getAuthManager();

        $media = $auth->createRole('media');
        $auth->update('upload', $media);

        // File.
        $fileUpdate = $auth->createPermission('fileUpdate');
        $fileUpdate->description = 'Update files';
        $auth->add($fileUpdate);

        $auth->addChild($media, $fileUpdate);

        $fileCreate = $auth->createPermission('fileCreate');
        $fileCreate->description = 'Upload or import new files';
        $auth->add($fileCreate);

        $auth->addChild($fileCreate, $fileUpdate);
        $auth->addChild($media, $fileCreate);

        $fileDelete = $auth->createPermission('fileDelete');
        $fileDelete->description = 'Delete files';
        $auth->add($fileDelete);

        $auth->addChild($fileDelete, $fileUpdate);
        $auth->addChild($media, $fileDelete);

        // Folder.
        $folderUpdate = $auth->createPermission('folderUpdate');
        $folderUpdate->description = 'Update folders';
        $auth->add($folderUpdate);

        $auth->addChild($media, $folderUpdate);

        $folderCreate = $auth->createPermission('folderCreate');
        $folderCreate->description = 'Create new folders';
        $auth->add($folderCreate);

        $auth->addChild($folderCreate, $folderUpdate);
        $auth->addChild($media, $folderCreate);

        $folderDelete = $auth->createPermission('folderDelete');
        $folderDelete->description = 'Delete folders';
        $auth->add($folderDelete);

        $auth->addChild($folderDelete, $folderUpdate);
        $auth->addChild($media, $folderDelete);

        $folderOrder = $auth->createPermission('folderOrder');
        $folderOrder->description = 'Change folder order';
        $auth->add($folderOrder);

        $auth->addChild($folderOrder, $folderUpdate);
        $auth->addChild($media, $folderOrder);
    }

    public function safeDown(): void
    {
        $auth = Yii::$app->getAuthManager();

        $this->delete($auth->itemTable, ['name' => 'folderDelete']);
        $this->delete($auth->itemTable, ['name' => 'folderCreate']);
        $this->delete($auth->itemTable, ['name' => 'folderOrder']);
        $this->delete($auth->itemTable, ['name' => 'folderUpdate']);

        $this->delete($auth->itemTable, ['name' => 'fileDelete']);
        $this->delete($auth->itemTable, ['name' => 'fileCreate']);
        $this->delete($auth->itemTable, ['name' => 'fileUpdate']);

        $upload = $auth->createRole('upload');
        $auth->update('media', $upload);
    }
}
