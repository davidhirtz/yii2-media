<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\traits;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\modules\admin\widgets\forms\fields\AssetPreview;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveField;

/**
 * @property AssetInterface $model
 */
trait AssetFieldsTrait
{
    public function renderHeader(): void
    {
        if ($previewField = $this->previewField()) {
            echo $previewField;
            echo $this->horizontalLine();
        }
    }

    public function previewField(): string
    {
        $preview = AssetPreview::widget(['asset' => $this->model]);
        return $this->row($this->offset($preview));
    }

    public function altTextField(?array $options = []): ActiveField|string
    {
        $language = ArrayHelper::remove($options, 'language');
        $attribute = $this->model->getI18nAttributeName('alt_text', $language);

        $options['inputOptions']['placeholder'] ??= $this->model->file->getI18nAttribute('alt_text', $language);

        return $this->field($this->model, $attribute, $options);
    }
}
