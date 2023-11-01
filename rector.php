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
        'davidhirtz\yii2\media\modules\admin\widgets\grid\FileGridView' => 'davidhirtz\yii2\media\modules\admin\widgets\grids\FileGridView',
        'davidhirtz\yii2\media\modules\admin\widgets\grid\FolderGridView' => 'davidhirtz\yii2\media\modules\admin\widgets\grids\FolderGridView',
        'davidhirtz\yii2\media\modules\admin\widgets\grid\TransformationGridView' => 'davidhirtz\yii2\media\modules\admin\widgets\grids\TransformationGridView',
        'davidhirtz\yii2\media\modules\admin\widgets\nav\Submenu' => 'davidhirtz\yii2\media\modules\admin\widgets\navs\Submenu',
        'davidhirtz\yii2\media\modules\admin\widgets\FileLinkButtonTrait' => 'davidhirtz\yii2\media\modules\admin\widgets\panels\traits\FileLinkButtonTrait',
        'davidhirtz\yii2\media\modules\admin\widgets\UploadTrait' => 'davidhirtz\yii2\media\modules\admin\widgets\grids\traits\UploadTrait',
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