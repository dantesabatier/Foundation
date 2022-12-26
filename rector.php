<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php70\Rector\FuncCall\RandomFunctionRector;
use Rector\Php71\Rector\FuncCall\CountOnNullRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;
use Rector\Php80\Rector\FunctionLike\UnionTypesRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . "/src"
    ]);
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
    ]);
    $rectorConfig->skip([
        CountOnNullRector::class => [
            __DIR__ . "/src/URLFileTypeMappingsInternal.php",
            __DIR__ . "/src/KeyValueCodingInternal.php",
            __DIR__ . "/src/Networking/DiskEntry.php",
        ],
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . "/src/Networking/TransferState.php",
            __DIR__ . "/src/Networking/URLProtocol.php",
            __DIR__ . "/src/Networking/URLSessionTask.php",
        ],
        JsonThrowOnErrorRector::class => [
            __DIR__ . "/src/Networking/URLRequest.php",
        ],
        ExplicitBoolCompareRector::class,
        RandomFunctionRector::class => [
            __DIR__ . "/src/SystemRandomNumberGenerator.php",
        ],
        ReadOnlyPropertyRector::class => [
            __DIR__ . "/src/ArrayConverter.php",
            __DIR__ . "/src/Networking/MultiHandle.php",
            __DIR__ . "/src/PropertyListSerializer.php",
        ],
        ReturnNeverTypeRector::class,
        NullToStrictStringFuncCallArgRector::class,
        UnionTypesRector::class,
        MixedTypeRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class
    ]);
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
    $rectorConfig->parallel(360, 2, 5);
    //$rectorConfig->disableParallel();
};
