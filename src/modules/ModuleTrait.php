<?php

namespace davidhirtz\yii2\media\modules;

use davidhirtz\yii2\media\Module;
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