<?php

namespace davidhirtz\yii2\media\modules;

use davidhirtz\yii2\media\Module;
use Yii;

/**
 * Trait ModuleTrait
 * @package davidhirtz\yii2\media\components
 */
trait ModuleTrait
{
    /**
     * @var Module
     */
    protected static $_module;

    /**
     * @return Module
     */
    public static function getModule()
    {
        if (static::$_module === null) {
            static::$_module = Yii::$app->getModule('media');
        }

        return static::$_module;
    }
}