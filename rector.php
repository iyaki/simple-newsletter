<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Class_\DynamicDocBlockPropertyToNativePropertyRector;
use Rector\CodeQuality\Rector\ClassMethod\ExplicitReturnNullRector;
use Rector\CodeQuality\Rector\Concat\DirnameDirConcatStringToDirectStringPathRector;
use Rector\CodeQuality\Rector\If_\CombineIfRector;
use Rector\CodeQuality\Rector\If_\ShortenElseIfRector;
use Rector\CodeQuality\Rector\LogicalAnd\LogicalToBooleanRector;
use Rector\CodingStyle\Rector\ArrowFunction\StaticArrowFunctionRector;
use Rector\CodingStyle\Rector\Assign\NestedTernaryToMatchRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Closure\StaticClosureRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\FuncCall\ArraySpreadInsteadOfArrayMergeRector;
use Rector\CodingStyle\Rector\FuncCall\StrictArraySearchRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php82\Rector\Param\AddSensitiveParameterAttributeRector;
use Rector\Php84\Rector\Class_\DeprecatedAnnotationToDeprecatedAttributeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamArrayDocblockBasedOnCallableNativeFuncCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamFromDimFetchKeyUseRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnArrayDocblockBasedOnArrayMapRector;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictArrayParamDimFetchRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/libs',
        __DIR__ . '/public',
        __DIR__ . '/bin',
        __DIR__ . '/config',
    ])
    // ->withSkip([
    //     DeprecatedAnnotationToDeprecatedAttributeRector::class,
    //     DeclareStrictTypesRector::class,
    //     MakeInheritedMethodVisibilitySameAsParentRector::class,
    //     StrictArraySearchRector::class,
    //     CatchExceptionNameMatchingTypeRector::class,
    //     EncapsedStringsToSprintfRector::class,
    //     ClassPropertyAssignToConstructorPromotionRector::class,
    //     RemoveAlwaysTrueIfConditionRector::class,
    //     CombineIfRector::class,
    //     ShortenElseIfRector::class,
    //     LogicalToBooleanRector::class,
    //     ExplicitReturnNullRector::class,
    //     NewlineBeforeNewAssignSetRector::class,
    //     NewlineAfterStatementRector::class,
    //     StrictArrayParamDimFetchRector::class,
    //     AddParamFromDimFetchKeyUseRector::class,
    // ])
    ->withImportNames(importShortClasses: false)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        phpunitCodeQuality: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        codingStyle: true,
        typeDeclarations: true,
        rectorPreset: true,
    )
    // ->withComposerBased(phpunit: true)
    // ->withAttributesSets(phpunit: true)
    ->withRules([
        ArraySpreadInsteadOfArrayMergeRector::class,
        StaticArrowFunctionRector::class,
        StringClassNameToClassConstantRector::class,
        DynamicDocBlockPropertyToNativePropertyRector::class,
        AddParamArrayDocblockBasedOnCallableNativeFuncCallRector::class,
        AddReturnArrayDocblockBasedOnArrayMapRector::class,
        JsonThrowOnErrorRector::class,
        StaticClosureRector::class,
        DirnameDirConcatStringToDirectStringPathRector::class,
    ])
    ->withConfiguredRule(AddSensitiveParameterAttributeRector::class, [
        AddSensitiveParameterAttributeRector::SENSITIVE_PARAMETERS => [
            'password',
            'newPassword',
            'oldPassword',
            'token',
            'username',
            'database',
        ],
    ])
    ->withPhpSets()
    // ->withCache(
    //     cacheClass: FileCacheStorage::class,
    //     cacheDirectory: __DIR__ . '/.cache/rector'
    // )
;
