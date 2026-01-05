<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules;

use Hirtz\Media\Module;
use Yii;

trait ModuleTrait
{
    public static function getModule(): Module
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('media');
        return $module;
    }
}
