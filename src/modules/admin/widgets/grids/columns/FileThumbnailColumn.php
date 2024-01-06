<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\columns;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\helpers\Html;
use yii\grid\DataColumn;

class FileThumbnailColumn extends DataColumn
{
    /**
     * @var int|null the width of the column
     */
    public ?int $columnWidth = 150;

    /**
     * @var callable|null a callback function that returns the route for the count link
     */
    public mixed $route = null;

    public function init(): void
    {
        if ($this->columnWidth) {
            Html::addCssStyle($this->headerOptions, ['width' => "{$this->columnWidth}px"]);
        }

        if (!is_callable($this->content)) {
            $this->content = $this->renderThumbnail(...);
        }

        parent::init();
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    protected function renderThumbnail(File $model, int $key, int $index): string
    {
        $content = $this->renderThumbnailContent($model);
        $route = is_callable($this->route) ? call_user_func($this->route, $model) : $this->route;

        if ($route) {
            $content = Html::a($content, $route);
        }

        return $content;
    }

    protected function renderThumbnailContent(File $file): string
    {
        if (!$file->hasPreview()) {
            return '';
        }

        return Html::tag('div', '', [
            'style' => 'background-image:url(' . ($file->getTransformationUrl('admin') ?: $file->getUrl()) . ');',
            'class' => 'thumb',
        ]);
    }
}
