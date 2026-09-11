<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use davidhirtz\yii2\datetime\DateTime;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Models\Actions\ReorderActiveRecords;
use Hirtz\Skeleton\Models\Interfaces\TrailModelInterface;
use Hirtz\Skeleton\Models\Trail;
use Override;

/**
 * @extends ReorderActiveRecords<Asset>
 */
class ReorderAssets extends ReorderActiveRecords
{
    use ModuleTrait;

    /**
     * @param list<int> $assetIds
     */
    public function __construct(protected AssetModelInterface $model, array $assetIds = [])
    {
        $assets = $this->model->getAssets()
            ->select(['id', 'position'])
            ->andWhere(['id' => $assetIds])
            ->orderBy(['position' => SORT_ASC])
            ->all();

        parent::__construct($assets, array_flip($assetIds));
    }

    #[Override]
    protected function afterReorder(): void
    {
        $message = Lang::t('media', 'REORDER_ASSETS_ASSET_ORDER_CHANGED');
        $now = new DateTime();

        $trail = $this->model instanceof TrailModelInterface
            ? Trail::createOrderTrail($this->model, $message)
            : null;

        $this->model->updated_at = $now;
        $this->model->update();

        foreach ($this->getTrailParents() as $parent) {
            Trail::createOrderTrail($parent, $message, $trail ? ['trail_id' => $trail->id] : []);

            $parent->setAttribute('updated_at', $now);
            $parent->update();
        }

        static::getModule()->invalidatePageCache();

        parent::afterReorder();
    }

    /**
     * The parents of the model, not of the assets: a section's entry is reordered with it, the file is not.
     *
     * @return list<ActiveRecord&TrailModelInterface>
     */
    protected function getTrailParents(): array
    {
        if (!$this->model instanceof TrailModelInterface) {
            return [];
        }

        $parents = array_filter(
            (array)$this->model->getTrailParents(),
            fn (mixed $parent): bool => $parent instanceof ActiveRecord && $parent instanceof TrailModelInterface
        );

        return array_values($parents);
    }
}
