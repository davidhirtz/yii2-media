<?php

namespace davidhirtz\yii2\media\modules;

use davidhirtz\yii2\media\Module;
use Yii;

trait ModuleTrait
{
    protected static ?Module $_module = null;

    /**
     * @return Module
     */
    public static function getModule(): Module
    {
        static::$_module ??= Yii::$app->getModule('media');
        return static::$_module;
    }
}