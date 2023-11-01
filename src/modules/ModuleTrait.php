<?php

namespace davidhirtz\yii2\media\modules;

use davidhirtz\yii2\media\Module;
use davidhirtz\yii2\skeleton\filters\PageCache;
use Yii;
use yii\caching\TagDependency;

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
    public static function getModule(): Module
    {
        static::$_module ??= Yii::$app->getModule('media');
        return static::$_module;
    }
}