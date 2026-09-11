<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Queries;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Db\ActiveQuery;

/**
 * @template T of Asset
 * @extends ActiveQuery<T>
 */
class AssetQuery extends ActiveQuery
{
    /**
     * Override this method to select only the attributes needed for frontend display.
     */
    public function selectSiteAttributes(): static
    {
        return $this->addSelect($this->prefixColumns(array_diff($this->getModelInstance()->getColumnAttributes(), [
            'updated_by_user_id',
            'created_at',
        ])));
    }

    /**
     * Override this method to select only the attributes needed for XML sitemap generation.
     */
    public function selectSitemapAttributes(): static
    {
        return $this->selectSiteAttributes();
    }

    public function withFiles(): static
    {
        return $this->with([
            'file' => function (FileQuery $query): void {
                $query->selectSiteAttributes()
                    ->withTranslations();
            }
        ]);
    }

    /**
     * @param class-string<AssetModelInterface> $modelClass
     */
    public function whereModelClass(string $modelClass): static
    {
        return $this->andWhere([$this->getTableAlias() . '.[[model_class]]' => $modelClass]);
    }

    public function whereModel(AssetModelInterface $model): static
    {
        return $this->whereModels([$model]);
    }

    /**
     * @param AssetModelInterface[] $models
     */
    public function whereModels(array $models): static
    {
        $alias = $this->getTableAlias();
        $ids = [];

        foreach ($models as $model) {
            $ids[$model->getAssetClass()::getModelClass()][] = $model->id;
        }

        if (!$ids) {
            return $this->andWhere('0=1');
        }

        $condition = ['or'];

        foreach ($ids as $modelClass => $modelIds) {
            $condition[] = [
                "$alias.[[model_class]]" => $modelClass,
                "$alias.[[model_id]]" => array_values(array_unique($modelIds)),
            ];
        }

        return $this->andWhere($condition);
    }
}
