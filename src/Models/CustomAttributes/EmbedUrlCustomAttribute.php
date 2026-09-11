<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\CustomAttributes;

use Hirtz\Skeleton\Models\CustomAttributes\UrlCustomAttribute;
use Override;
use yii\base\Model;

class EmbedUrlCustomAttribute extends UrlCustomAttribute
{
    /**
     * A `filter` rule with an array callable, never an inline closure: {@see \yii\validators\InlineValidator} rebinds
     * a closure to the model, which fails for one created in the scope of this object.
     */
    #[Override]
    protected function getValidationRules(Model $owner): array
    {
        return [
            ...parent::getValidationRules($owner),
            ['filter', 'filter' => [$this, 'sanitize']],
        ];
    }

    public function sanitize(mixed $url): mixed
    {
        if (!is_string($url) || $url === '') {
            return $url;
        }

        if (preg_match('~^https://vimeo.com/(\d+)~', $url, $matches)) {
            return "https://player.vimeo.com/video/$matches[1]";
        }

        if (str_contains($url, '/watch?v=')) {
            $url = str_replace('/watch?v=', '/embed/', $url);
            return preg_replace('/&/', '?', $url, 1);
        }

        if (str_contains($url, 'youtu.be/')) {
            $url = str_replace('youtu.be/', 'www.youtube.com/embed/', $url);
        }

        if (str_contains($url, 'youtube.com/live/')) {
            $url = str_replace('youtube.com/live/', 'youtube.com/embed/', $url);
        }

        return $url;
    }
}
