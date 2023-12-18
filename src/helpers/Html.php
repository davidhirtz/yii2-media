<?php

namespace davidhirtz\yii2\media\helpers;

use davidhirtz\yii2\media\modules\ModuleTrait;
use Yii;
use yii\helpers\BaseHtml;
use yii\helpers\Url;

class Html extends BaseHtml
{
    use ModuleTrait;

    public static function a($text, $url = null, $options = []): string
    {
        if (!$url) {
            return parent::tag('span', $text, $options);
        }

        if (is_array($url)) {
            $url = Url::toRoute($url);
        }

        $host = trim(parse_url($url, PHP_URL_HOST) ?? '');

        if ((!empty($host) && $host !== Yii::$app->getRequest()->getHostName())) {
            $options['target'] ??= '_blank';
            $options['rel'] ??= 'noopener';
        }

        if (str_contains($url, static::getModule()->baseUrl)) {
            $options['download'] = true;
        }

        return parent::a($text, $url, $options);
    }
}
