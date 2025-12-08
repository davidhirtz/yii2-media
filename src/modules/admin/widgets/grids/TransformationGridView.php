<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\grids;

use Hirtz\Media\models\Transformation;
use Hirtz\Media\modules\admin\widgets\grids\columns\FileThumbnailColumn;
use Hirtz\Media\modules\admin\widgets\traits\FileWidgetTrait;
use Hirtz\Media\modules\ModuleTrait;
use Hirtz\Skeleton\html\Button;
use Hirtz\Skeleton\html\Div;
use Hirtz\Skeleton\widgets\grids\columns\ButtonColumn;
use Hirtz\Skeleton\widgets\grids\columns\Column;
use Hirtz\Skeleton\widgets\grids\columns\DataColumn;
use Hirtz\Skeleton\widgets\grids\columns\RelativeTimeColumn;
use Hirtz\Skeleton\widgets\grids\GridView;
use Override;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;

/**
 * @extends GridView<Transformation>
 * @property ActiveDataProvider|ArrayDataProvider|null $provider
 */
class TransformationGridView extends GridView
{
    use ModuleTrait;
    use FileWidgetTrait;

    public string $layout = '{items}{footer}';

    #[Override]
    public function configure(): void
    {
        $this->model ??= Transformation::instance();

        $this->provider ??= new ArrayDataProvider([
            'allModels' => $this->file->getTransformations()
                ->orderBy(['width' => SORT_DESC, 'size' => SORT_DESC])
                ->indexBy('id')
                ->all(),
            'pagination' => false,
            'sort' => false,
        ]);

        $this->columns ??= [
            $this->getThumbnailColumn(),
            $this->getNameColumn(),
            $this->getDimensionsColumn(),
            $this->getSizeColumn(),
            $this->getCreatedAtColumn(),
            $this->getButtonsColumn(),
        ];

        parent::configure();
    }

    public function getThumbnailColumn(): Column
    {
        return FileThumbnailColumn::make()
            ->url(fn (Transformation $transformation) => $transformation->getFileUrl())
            ->linkAttributes(['target' => '_blank']);
    }

    public function getNameColumn(): Column
    {
        return DataColumn::make()
            ->property('name')
            ->content(fn (Transformation $transformation) => Div::make()
                ->content($transformation->getDisplayName())
                ->class('strong'));
    }

    public function getDimensionsColumn(): Column
    {
        return DataColumn::make()
            ->property('dimensions')
            ->content(fn (Transformation $transformation): string => $transformation->width && $transformation->height
                ? ($transformation->width . ' x ' . $transformation->height)
                : '');
    }

    public function getSizeColumn(): Column
    {
        return DataColumn::make()
            ->property('size')
            ->format('shortSize');
    }

    public function getCreatedAtColumn(): Column
    {
        return RelativeTimeColumn::make()
            ->property('created_at');
    }

    public function getButtonsColumn(): Column
    {
        return ButtonColumn::make()
            ->content($this->getButtonsColumnContent(...));
    }

    protected function getButtonsColumnContent(Transformation $transformation): array
    {
        return [
            Button::make()
                ->danger()
                ->icon('trash')
                ->post(['transformation/delete', 'id' => $transformation->id])
        ];
    }

    #[Override]
    public function isSortable(): bool
    {
        return false;
    }
}
