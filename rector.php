<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php81\Rector\ClassConst\FinalizePublicClassConstantRector;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationBasedOnParentClassMethodRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictScalarReturnExprRector;
use Rector\TypeDeclaration\Rector\Property\AddPropertyTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    $rectorConfig->ruleWithConfiguration(RenameClassRector::class, [
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\CounterColumn' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\columns\CounterColumn',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\MessageSourceTrait' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\traits\MessageSourceTrait',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\StatusGridViewTrait' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\traits\StatusGridViewTrait',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\TypeGridViewTrait' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\traits\TypeGridViewTrait',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\GridView',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\LogGridView' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\LogGridView',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\RedirectGridView' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\RedirectGridView',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\TrailGridView' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\TrailGridView',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\UserGridView' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\UserGridView',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\grid\UserLoginGridView' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\grids\UserLoginGridView',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\nav\UserLoginGridView' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\navs\NavBar',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\nav\TrailSubmenu' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\navs\TrailSubmenu',
        'davidhirtz\yii2\skeleton\modules\admin\widgets\nav\UserSubmenu' => 'davidhirtz\yii2\skeleton\modules\admin\widgets\navs\UserSubmenu',
    ]);

    $rectorConfig->rules([
        AddReturnTypeDeclarationBasedOnParentClassMethodRector::class,
        AddPropertyTypeDeclarationRector::class,
        AddReturnTypeDeclarationRector::class,
        InlineConstructorDefaultToPropertyRector::class,
        ReturnTypeFromStrictNativeCallRector::class,
        ReturnTypeFromStrictScalarReturnExprRector::class,
        StringClassNameToClassConstantRector::class,
        TypedPropertyFromAssignsRector::class,
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81
    ]);

    $rectorConfig->skip([
        __DIR__ . '/messages',
        FinalizePublicClassConstantRector::class,
    ]);
};