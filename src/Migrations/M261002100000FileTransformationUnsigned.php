<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Override;
use yii\db\Migration;

/**
 * Makes `file_transformation.width`, `height` and `size` unsigned like the file's own: a signed `smallint` refused
 * a derivative wider than 32767 pixels, which the file itself may be.
 *
 * @noinspection PhpUnused
 */
class M261002100000FileTransformationUnsigned extends Migration
{
    #[Override]
    public function safeUp(): void
    {
        $this->alterColumn('{{%file_transformation}}', 'width', 'smallint(6) unsigned DEFAULT NULL');
        $this->alterColumn('{{%file_transformation}}', 'height', 'smallint(6) unsigned DEFAULT NULL');
        $this->alterColumn('{{%file_transformation}}', 'size', 'bigint(20) unsigned NOT NULL DEFAULT 0');
    }

    #[Override]
    public function safeDown(): void
    {
        $this->alterColumn('{{%file_transformation}}', 'size', 'bigint(20) NOT NULL DEFAULT 0');
        $this->alterColumn('{{%file_transformation}}', 'height', 'smallint(6) DEFAULT NULL');
        $this->alterColumn('{{%file_transformation}}', 'width', 'smallint(6) DEFAULT NULL');
    }
}
