<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Skeleton\Db\Commands\RenumberPositions;
use Override;
use yii\db\Migration;

/**
 * Deletes before 3.1.1 left gaps, which the admin subtitle reads as a rank out of the parent's count.
 *
 * @noinspection PhpUnused
 */
class M260924100000RenumberAssetPositions extends Migration
{
    #[Override]
    public function safeUp(): void
    {
        (new RenumberPositions($this->getDb(), 'asset', ['model_class', 'model_id']))->execute();
    }

    /**
     * The old gaps carried no meaning, so there is nothing to restore.
     */
    #[Override]
    public function safeDown(): void
    {
    }
}
