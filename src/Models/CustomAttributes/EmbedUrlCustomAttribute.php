<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\CustomAttributes;

use Hirtz\Skeleton\Models\CustomAttributes\UrlCustomAttribute;
use Override;
use yii\base\Model;

class EmbedUrlCustomAttribute extends UrlCustomAttribute
{
    protected array $schemes = ['http', 'https'];

    /**
     * An array callable, since {@see \yii\validators\InlineValidator} rebinds a closure to the model, which fails here.
     */
    #[Override]
    protected function getValidationRules(Model $owner): array
    {
        return [
            ...parent::getValidationRules($owner),
            ['filter', 'filter' => $this->sanitize(...)],
        ];
    }

    public function sanitize(mixed $url): mixed
    {
        if (!is_string($url) || $url === '') {
            return $url;
        }

        if (preg_match('~^https://vimeo\.com/(\d+)(?:/([\da-f]+))?~', $url, $matches)) {
            return "https://player.vimeo.com/video/$matches[1]" . (isset($matches[2]) ? "?h=$matches[2]" : '');
        }

        if (preg_match('~^(https?://[^/]*youtube\.com)/watch\?([^#]*)~', $url, $matches)) {
            $params = explode('&', $matches[2]);

            foreach ($params as $i => $param) {
                if (str_starts_with($param, 'v=')) {
                    unset($params[$i]);
                    return "$matches[1]/embed/" . substr($param, 2) . ($params ? '?' . implode('&', $params) : '');
                }
            }
        }

        if (str_contains($url, 'youtu.be/')) {
            $url = str_replace('youtu.be/', 'www.youtube.com/embed/', $url);
        }

        if (str_contains($url, 'youtube.com/live/')) {
            $url = str_replace('youtube.com/live/', 'youtube.com/embed/', $url);
        }

        if (str_contains($url, 'youtube.com/shorts/')) {
            $url = str_replace('youtube.com/shorts/', 'youtube.com/embed/', $url);
        }

        return $url;
    }
}
