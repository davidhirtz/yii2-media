<?php

declare(strict_types=1);

namespace Hirtz\Media\Helpers;

use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Web\Request;
use Override;
use Yii;
use yii\helpers\BaseHtml;

;

class Html extends BaseHtml
{
    use ModuleTrait;

    /**
     * @param array<int|string, mixed>|string|null $url
     * @param array<string, mixed> $options
     */
    #[Override]
    public static function a($text, $url = null, $options = []): string
    {
        if (!$url) {
            return parent::tag('span', $text, $options);
        }

        if (is_array($url)) {
            $url = Url::toRoute($url);
        }

        self::prepareLinkOptions($url, $options);

        return parent::a($text, $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function prepareLinkOptions(string $url, array &$options): void
    {
        $host = trim((string)(parse_url($url, PHP_URL_HOST) ?: ''));

        if ($host !== '' && $host !== Request::current()?->getHostName()) {
            $options['target'] ??= '_blank';
            $options['rel'] ??= 'noopener';
        }

        if (str_contains($url, (string)static::getModule()->baseUrl) || str_contains($url, (string)static::getModule()->uploadPath)) {
            $options['download'] ??= true;
        }
    }
}
