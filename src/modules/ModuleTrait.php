<?php

declare(strict_types=1);

namespace Hirtz\Media\modules;

use Hirtz\Media\Module;
use Yii;

trait ModuleTrait
{
    protected static ?Module $_module = null;

    public static function getModule(): Module
    {
        if (static::$_module === null) {
            /** @var Module $module */
            $module = Yii::$app->getModule('media');
            static::$_module ??= $module;
        }

        return static::$_module;
    }
}
