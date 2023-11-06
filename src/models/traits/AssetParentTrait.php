<?php

namespace davidhirtz\yii2\media\models\traits;

use davidhirtz\yii2\cms\models\traits\VisibleAttributeTrait;
use davidhirtz\yii2\media\Module;
use Yii;

trait AssetParentTrait
{
    use VisibleAttributeTrait;

    private ?array $_breakpoints = null;

    public function formatSizes(array|string|null $sizes = null, ?array $breakpoints = null): string
    {
        if (!is_array($sizes)) {
            return $sizes ?? '100vw';
        }

        $breakpoints ??= $this->getBreakpoints();
        $widths = [];

        foreach ($sizes as $condition => $width) {
            if ($breakpoints[$condition] ?? false) {
                $maxWidth = $breakpoints[$condition] - 1;
                $condition = "(max-width:{$maxWidth}px)";
            }

            if (is_string($condition)) {
                $width = "$condition $width";
            }

            $widths[] = $width;
        }

        return implode(',', $widths);
    }

    public function getSizes(): ?string
    {
        return $this->formatSizes($this->getTypeOptions()['sizes'] ?? null);
    }

    public function getTransformationNames(): array
    {
        return $this->getTypeOptions()['transformations'] ?? [];
    }

    public function getVisibleAssets(): array
    {
        return $this->isAttributeVisible('#assets') ? $this->assets : [];
    }

    public function getBreakpoints(): ?array
    {
        if ($this->_breakpoints === null) {
            /** @var Module $module */
            $module = Yii::$app->getModule('media');
            $this->_breakpoints = $module->breakpoints;
        }

        return $this->_breakpoints;
    }
}