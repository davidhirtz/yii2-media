<?php

namespace davidhirtz\yii2\media\models\traits;

trait EmbedUrlTrait
{
    public function validateEmbedUrl(): void
    {
        foreach ($this->getI18nAttributesNames('embed_url') as $attributeName) {
            if ($attribute = $this->$attributeName) {
                $this->$attributeName = $this->getSanitizedEmbedUrl($attribute);
            }
        }
    }

    protected function getSanitizedEmbedUrl(string $url): string
    {
        if (preg_match('~^https://vimeo.com/(\d+)~', $url, $matches)) {
            return "https://player.vimeo.com/video/$matches[1]";
        }

        return str_replace('/watch?v=', '/embed/', $url);
    }


    protected function getFormattedEmbedUrl(?string $language = null): string
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
