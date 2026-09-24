<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Skeleton\Db\Commands\RenumberPositions;
use Override;
use yii\db\Migration;

/**
 * Closes the gaps deletes left in asset positions before 3.1.1, which renumbers on every delete and move: the
 * admin subtitle reads a position as its rank out of the parent's count.
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
