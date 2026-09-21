<?php

declare(strict_types=1);

namespace Hirtz\Media\Migrations;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Db\Traits\MigrationTrait;
use Hirtz\Skeleton\Models\Search;
use yii\db\ActiveRecord;
use yii\db\Migration;
use yii\db\Query;

/**
 * A model holds a file once. Before v3 it could hold the same file any number of times, so the duplicates an
 * installation already has are removed here — the row with the lowest position wins — before the unique index
 * makes another one impossible.
 *
 * @noinspection PhpUnused
 */
class M260916100000AssetUnique extends Migration
{
    use MigrationTrait;

    private const string INDEX = 'model_file';

    public function safeUp(): void
    {
        $ids = $this->getDuplicateAssetIds();

        if ($ids) {
            $this->deleteAssets($ids);
            $this->deleteSearchDocuments($ids);
            $this->recalculateFileAssetCounts();
            $this->recalculateModelAssetCounts();
        }

        $this->createIndexIfMissing(self::INDEX, Asset::tableName(), ['model_class', 'model_id', 'file_id'], true);
    }

    public function safeDown(): void
    {
        $this->dropIndex(self::INDEX, Asset::tableName());
    }

    /**
     * Every row of a (model, file) group but the one that comes first — lowest position, then lowest id, which is
     * the one the record has shown at the top of its asset list all along.
     *
     * @return list<int>
     */
    protected function getDuplicateAssetIds(): array
    {
        $assets = $this->getQuotedTableName(Asset::tableName());

        $ids = $this->getDb()->createCommand("
            SELECT DISTINCT [[a]].[[id]] FROM $assets [[a]] JOIN $assets [[b]]
                ON [[b]].[[model_class]] = [[a]].[[model_class]]
               AND [[b]].[[model_id]] = [[a]].[[model_id]]
               AND [[b]].[[file_id]] = [[a]].[[file_id]]
               AND ([[b]].[[position]] < [[a]].[[position]]
                   OR ([[b]].[[position]] = [[a]].[[position]] AND [[b]].[[id]] < [[a]].[[id]]))
        ")->queryColumn();

        return array_values(array_map(intval(...), $ids));
    }

    /**
     * @param list<int> $ids
     */
    protected function deleteAssets(array $ids): void
    {
        foreach (array_chunk($ids, 2000) as $chunk) {
            $this->delete(Asset::tableName(), ['id' => $chunk]);
        }

        echo '    > removed ' . count($ids) . " duplicate assets\n";
    }

    /**
     * The asset classes are read off the `search` table rather than off the media module, so an installation that
     * has since dropped a bundle still has its documents collected.
     *
     * @param list<int> $ids
     */
    protected function deleteSearchDocuments(array $ids): void
    {
        $classes = (new Query())
            ->select(['model_class'])
            ->distinct()
            ->from(Search::tableName())
            ->column();

        $classes = array_values(array_filter(
            $classes,
            fn (mixed $class): bool => is_string($class) && is_a($class, Asset::class, true)
        ));

        if (!$classes) {
            return;
        }

        $rows = 0;

        foreach (array_chunk($ids, 2000) as $chunk) {
            $rows += $this->getDb()->createCommand()
                ->delete(Search::tableName(), ['model_class' => $classes, 'model_id' => $chunk])
                ->execute();
        }

        echo "    > removed $rows search documents\n";
    }

    protected function recalculateFileAssetCounts(): void
    {
        $assets = $this->getQuotedTableName(Asset::tableName());
        $files = $this->getQuotedTableName(File::tableName());

        $rows = $this->getDb()->createCommand("
            UPDATE $files [[f]]
            SET [[f]].[[asset_count]] = (SELECT COUNT(*) FROM $assets [[a]] WHERE [[a]].[[file_id]] = [[f]].[[id]])
        ")->execute();

        echo "    > corrected the asset count of $rows files\n";
    }

    /**
     * The model tables come from the `model_class` of the surviving rows — a migration cannot ask the media module,
     * whose registered classes are the configuration of the moment rather than of this point in history.
     */
    protected function recalculateModelAssetCounts(): void
    {
        $assets = $this->getQuotedTableName(Asset::tableName());

        $classes = (new Query())
            ->select(['model_class'])
            ->distinct()
            ->from(Asset::tableName())
            ->column();

        foreach ($classes as $class) {
            if (!is_string($class) || !is_a($class, ActiveRecord::class, true)) {
                continue;
            }

            $table = $class::tableName();

            if (!$this->getDb()->getTableSchema($table, true) || !$this->hasColumn($table, 'asset_count')) {
                continue;
            }

            $models = $this->getQuotedTableName($table);
            $quoted = $this->getDb()->quoteValue($class);

            $rows = $this->getDb()->createCommand("
                UPDATE $models [[m]]
                SET [[m]].[[asset_count]] = (SELECT COUNT(*) FROM $assets [[a]]
                    WHERE [[a]].[[model_id]] = [[m]].[[id]] AND [[a]].[[model_class]] = $quoted)
            ")->execute();

            echo "    > corrected the asset count of $rows $class records\n";
        }
    }
}
