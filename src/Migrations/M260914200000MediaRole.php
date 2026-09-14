<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use Hirtz\Skeleton\Models\User;
use yii\db\Migration;

/**
 * The `media` role grouped nothing but its two permissions, which every parent and every assignee of the role
 * receives directly before it is dropped.
 *
 * @noinspection PhpUnused
 */
class M260914200000MediaRole extends Migration
{
    use MigrationTrait;

    private const string ROLE_MEDIA = 'media';

    public function safeUp(): void
    {
        $auth = $this->getAuthManager();
        $media = $auth->getRole(self::ROLE_MEDIA);

        if ($media === null) {
            return;
        }

        foreach ([File::AUTH_FILE, Folder::AUTH_FOLDER] as $name) {
            $this->inheritRole($name);
        }

        $auth->remove($media);
        $auth->invalidateCache();
    }

    /**
     * The assignments the role itself carried are gone for good: a role and a permission are assigned the same way,
     * so nothing records which of the two an installation meant.
     */
    public function safeDown(): void
    {
        $auth = $this->getAuthManager();

        $media = $auth->createRole(self::ROLE_MEDIA);
        $auth->add($media);

        $auth->addChild($auth->getRole(User::AUTH_ROLE_ADMIN), $media);
        $auth->addChild($media, $auth->getPermission(File::AUTH_FILE));
        $auth->addChild($media, $auth->getPermission(Folder::AUTH_FOLDER));

        $auth->invalidateCache();
    }

    private function inheritRole(string $name): void
    {
        $auth = $this->getAuthManager();
        $db = $this->getDb();

        $item = $db->quoteValue($name);
        $role = $db->quoteValue(self::ROLE_MEDIA);

        $itemChildTable = $this->getQuotedTableName($auth->itemChildTable);
        $assignmentTable = $this->getQuotedTableName($auth->assignmentTable);

        $this->execute("
            INSERT IGNORE INTO $itemChildTable ([[parent]], [[child]])
            SELECT DISTINCT [[parent]], $item
            FROM $itemChildTable
            WHERE [[child]] = $role
        ");

        $this->execute("
            INSERT IGNORE INTO $assignmentTable ([[item_name]], [[user_id]], [[created_at]])
            SELECT DISTINCT $item, [[user_id]], " . time() . "
            FROM $assignmentTable
            WHERE [[item_name]] = $role
        ");
    }
}
