<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\ConvertStaticToSelfRector;
use Rector\CodeQuality\Rector\ClassMethod\ExplicitReturnNullRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\ClassConstFetch\ClassOnThisVariableObjectRector;
use Rector\Php80\Rector\FuncCall\ClassOnObjectRector;
use Rector\Php81\Rector\ClassMethod\NewInInitializerRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . "/src",
        ])->withPhpSets(php84: true)->withSkip([
            SensitiveConstantNameRector::class,
            ClassPropertyAssignToConstructorPromotionRector::class,
            ExplicitBoolCompareRector::class,
            FlipTypeControlToUseExclusiveTypeRector::class,
            DisallowedEmptyRuleFixerRector::class,
            LocallyCalledStaticMethodToNonStaticRector::class,
            RemoveUnusedPrivateMethodRector::class,
            RemoveUnusedPrivatePropertyRector::class => [
                __DIR__ . "/src/Networking/URLSessionTask.php",
            ],
            RemoveUnusedPrivateMethodParameterRector::class,
            RemoveUselessReturnTagRector::class,
            RemoveUselessParamTagRector::class,
            RemoveAlwaysTrueIfConditionRector::class => [
                __DIR__ . "/src/URL.php",
                __DIR__ . "/src/StandardAdditions.php"
            ],
            ExplicitReturnNullRector::class, RestoreDefaultNullToNullableTypePropertyRector::class,
            ReadOnlyPropertyRector::class,
            ClassOnThisVariableObjectRector::class,
            ClassOnObjectRector::class,
            RemoveEmptyClassMethodRector::class => [
                __DIR__ . "/src/ArrayConverter.php"
            ],
            RemoveUnusedPromotedPropertyRector::class => [
                __DIR__ . "/src/ArrayConverter.php"
            ],
            ConvertStaticToSelfRector::class => [
                __DIR__ . "/src/ValueTransformer.php"
            ],
            NullToStrictStringFuncCallArgRector::class => [
                __DIR__ . "/src/URLAdditions.php"
            ],
            NewInInitializerRector::class => [
                __DIR__ . "/src/Progress.php"
            ]
        ])->withPreparedSets(deadCode: true, codeQuality: true, earlyReturn: true);
} catch (InvalidConfigurationException $e) {
    error_log($e->getMessage());
}
