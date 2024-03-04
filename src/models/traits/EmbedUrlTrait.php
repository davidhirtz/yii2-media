<?php

namespace davidhirtz\yii2\media\models\traits;

use davidhirtz\yii2\skeleton\models\traits\I18nAttributesTrait;
use Yii;

/**
 * @mixin I18nAttributesTrait
 */
trait EmbedUrlTrait
{
    public ?int $embedUrlLength = 255;

    public function getEmbedUrlTraitAttributeLabels(): array
    {
        return [
            'embed_url' => Yii::t('media', 'Embed URL'),
        ];
    }

    public function getEmbedUrlTraitRules(): array
    {
        return $this->getI18nRules([
            [
                ['embed_url'],
                'url',
            ],
            [
                ['embed_url'],
                $this->validateEmbedUrl(...),
            ],
            [
                ['embed_url'],
                'string',
                'max' => $this->embedUrlLength,
            ],
        ]);
    }

    public function validateEmbedUrl(string $attributeName): void
    {
        if ($attribute = $this->$attributeName) {
            $this->$attributeName = $this->sanitizeEmbedUrl($attribute);
        }
    }

    protected function sanitizeEmbedUrl(string $url): string
    {
        if (preg_match('~^https://vimeo.com/(\d+)~', $url, $matches)) {
            return "https://player.vimeo.com/video/$matches[1]";
        }

        return str_replace('/watch?v=', '/embed/', $url);
    }

    public function getFormattedEmbedUrl(?string $language = null): string
    {
        if (!$link = $this->getI18nAttribute('embed_url', $language)) {
            return '';
        }

        $link .= (str_contains((string)$link, '?') ? '&' : '?') . 'autoplay=1';

        if (strpos($link, 'youtube')) {
            $link .= '&disablekb=1&modestbranding=1&rel=0';
        }

        if (strpos($link, 'vimeo')) {
            $link .= '&dnt=1';
        }

        return $link;
    }
}
