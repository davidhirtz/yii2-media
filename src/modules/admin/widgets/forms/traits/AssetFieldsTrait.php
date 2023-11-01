<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\traits;

use davidhirtz\yii2\media\models\AssetInterface;
use davidhirtz\yii2\media\models\AssetParentInterface;
use davidhirtz\yii2\skeleton\widgets\forms\ActiveFormTrait;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveField;

/**
 * AssetFieldsTrait allows extensions that use the {@see AssetParentInterface} to implement a full width preview
 * field to {@see ActiveFormTrait}.
 *
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
        $file = $this->model->file;

        if ($file->hasPreview()) {
            $image = Html::img($file->getUrl(), [
                'id' => 'image',
                'class' => 'img-transparent',
            ]);

            return $this->row($this->offset(!($width = $this->model->file->width) ? $image : Html::tag('div', $image, [
                'style' => "max-width:{$width}px",
            ])));
        }

        return '';
    }

    public function altTextField(?array $options = []): ActiveField|string
    {
        $language = ArrayHelper::remove($options, 'language');
        $attribute = $this->model->getI18nAttributeName('alt_text', $language);

        if (!isset($options['inputOptions']['placeholder'])) {
            $options['inputOptions']['placeholder'] = $this->model->file->getI18nAttribute('alt_text', $language);
        }

        return $this->field($this->model, $attribute, $options);
    }


    /**
     * Returns a list of default field names. This array excludes generated i18n fields as the field methods should
     * already take care of translations.
     */
    public function getDefaultFieldNames(): array
    {
        $defaultOrder = [
            'status',
            'type',
            'name',
            'content',
            'alt_text',
            'link',
        ];

        $languages = array_diff(Yii::$app->getI18n()->languages, [Yii::$app->sourceLanguage]);
        $i18nAttributes = [];

        foreach ($this->model->i18nAttributes as $attribute) {
            $i18nAttributes = array_merge($i18nAttributes, $this->model->getI18nAttributesNames($attribute, $languages));
        }

        return array_unique([...$defaultOrder, ...array_diff($i18nAttributes, $this->model->safeAttributes())]);
    }
}