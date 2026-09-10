<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\CodeQuality\Rector\Class_\ConvertStaticToSelfRector;
use Rector\CodeQuality\Rector\ClassMethod\ExplicitReturnNullRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Equal\UseIdenticalOverEqualWithSameTypeRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\ObjectExplicitBoolCompareRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveMixedDocblockOverruledByNativeTypeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessUnionReturnDocblockRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\DeadCode\Rector\MethodCall\RemoveNullArgOnNullDefaultParamRector;
use Rector\DeadCode\Rector\Property\RemoveDefaultValueFromAssignedPropertyRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\DeadCode\Rector\StmtsAwareInterface\RemoveDeadInstanceOfAssertRector;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\ClassConstFetch\ClassOnThisVariableObjectRector;
use Rector\Php80\Rector\FuncCall\ClassOnObjectRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . "/src",
        ])->withPhpSets()->withSkip([
            SensitiveConstantNameRector::class,
            ClassPropertyAssignToConstructorPromotionRector::class,
            FlipTypeControlToUseExclusiveTypeRector::class,
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
            ExplicitReturnNullRector::class,
            RestoreDefaultNullToNullableTypePropertyRector::class,
            ReadOnlyPropertyRector::class,
            ClassOnThisVariableObjectRector::class,
            ClassOnObjectRector::class,
            RemoveEmptyClassMethodRector::class,
            RemoveUnusedPublicMethodParameterRector::class,
            RemoveMixedDocblockOverruledByNativeTypeRector::class,
            RemoveUselessUnionReturnDocblockRector::class,
            RemoveDeadInstanceOfAssertRector::class,
            RemoveDefaultValueFromAssignedPropertyRector::class,
            RemoveUnusedPromotedPropertyRector::class => [
                __DIR__ . "/src/ArrayConverter.php"
            ],
            ConvertStaticToSelfRector::class => [
                __DIR__ . "/src/ValueTransformer.php"
            ],
            UseIdenticalOverEqualWithSameTypeRector::class => [
                __DIR__ . "/src/StandardAdditions.php"
            ],
            ThrowWithPreviousExceptionRector::class => [
                __DIR__ . "/src/Predicates/PredicateScanner.php"
            ],
            RecastingRemovalRector::class => [
                __DIR__ . "/src/Predicates/PredicateUtilities.php"
            ],
            // tests/ is not in withPaths(), but running Rector over it by hand is worth doing, and this rule is a trap when you do. Both files pass the default explicitly because passing it is what the test asserts: testAnObserverWithoutAnObjectAcceptsAnySender posts once with a sender and once with an explicit null. Dropping the argument collapses the two cases into one, and the suite still passes while covering half of what it claims.
            RemoveNullArgOnNullDefaultParamRector::class => [
                __DIR__ . "/tests/ConditionalAndBlockExpressionTest.php",
                __DIR__ . "/tests/NotificationCenterTest.php"
            ],
            ObjectExplicitBoolCompareRector::class
        ])->withPreparedSets(deadCode: true, codeQuality: true, earlyReturn: true);
} catch (InvalidConfigurationException $e) {
    error_log($e->getMessage());
}
