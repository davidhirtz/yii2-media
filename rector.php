<?php


declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationBasedOnParentClassMethodRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;
use Rector\TypeDeclaration\Rector\Property\AddPropertyTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPhpSets(php81: true)
    ->withRules([
        AddReturnTypeDeclarationBasedOnParentClassMethodRector::class,
        AddPropertyTypeDeclarationRector::class,
        AddReturnTypeDeclarationRector::class,
        DeclareStrictTypesRector::class,
        InlineConstructorDefaultToPropertyRector::class,
        ReturnTypeFromStrictNativeCallRector::class,
        StringClassNameToClassConstantRector::class,
        TypedPropertyFromAssignsRector::class,
    ])
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);
