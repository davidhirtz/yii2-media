<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\traits;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\modules\admin\widgets\forms\fields\AssetPreview;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveField;

/**
 * @property ActiveRecord&AssetInterface $model
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
        $html = AssetPreview::widget(['asset' => $this->model]);
        return $html ? $this->row($this->offset($html)) : '';
    }

    public function altTextField(?array $options = []): ActiveField|string
    {
        $language = ArrayHelper::remove($options, 'language');

        $attribute = method_exists($this->model, 'getI18nAttributeName')
            ? $this->model->getI18nAttributeName('alt_text', $language)
            : 'alt_text';

        $options['inputOptions']['placeholder'] ??= $this->model->file->getI18nAttribute('alt_text', $language);

        return $this->field($this->model, $attribute, $options);
    }
}
