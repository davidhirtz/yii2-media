<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use Hirtz\Skeleton\Models\Translation;
use yii\db\Migration;

/**
 * Moves the translated attributes of {@see File} from their `_xx` columns into {@see Translation} records.
 *
 * @noinspection PhpUnused
 */
class M260910130000Translations extends Migration
{
    use MigrationTrait;

    public function safeUp(): void
    {
        $this->moveI18nColumnsToTranslations(File::create());
    }

    public function safeDown(): void
    {
        $this->restoreI18nColumnsFromTranslations(File::create());
    }
}
