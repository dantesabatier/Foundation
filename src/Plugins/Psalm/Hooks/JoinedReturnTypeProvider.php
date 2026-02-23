<?php

namespace Sabatier\Foundation\Plugins\Psalm\Hooks;

use Override;
use PhpParser\Node\Expr\MethodCall;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type;
use Psalm\Type\Atomic;
use Psalm\Type\Atomic\TArray;
use Psalm\Type\Atomic\TBool;
use Psalm\Type\Atomic\TGenericObject;
use Psalm\Type\Atomic\TIterable;
use Psalm\Type\Atomic\TKeyedArray;
use Psalm\Type\Atomic\TLiteralFloat;
use Psalm\Type\Atomic\TLiteralInt;
use Psalm\Type\Atomic\TLiteralString;
use Psalm\Type\Union;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FlattenSequence;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\Slice;

final class JoinedReturnTypeProvider implements MethodReturnTypeProviderInterface
{
    /**
     * @var list<class-string>
     */
    private const array baseSequenceClases = [
        ArrayClass::class,
        Dictionary::class,
        Set::class,
        Slice::class,
    ];

    /**
     * @return list<class-string>
     */
    #[Override]
    public static function getClassLikeNames(): array
    {
        return self::baseSequenceClases;
    }

    #[Override]
    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Union
    {
        $methodName = $event->getMethodNameLowercase();
        if ($methodName !== "joined") {
            return null;
        }
        $statement = $event->getStmt();
        if (!$statement instanceof MethodCall) {
            return null;
        }
        $source = $event->getSource();
        $typeProvider = $source->getNodeTypeProvider();
        $receiverType = $typeProvider->getType($statement->var);
        if (!$receiverType) {
            return null;
        }
        $allElementTypes = [];
        foreach ($receiverType->getAtomicTypes() as $receiverAtomic) {
            $innerCollectionUnion = self::getValueType($receiverAtomic);
            if (!$innerCollectionUnion) {
                continue;
            }
            foreach ($innerCollectionUnion->getAtomicTypes() as $innerAtomic) {
                $elementUnion = self::getValueType($innerAtomic);
                if ($elementUnion) {
                    foreach ($elementUnion->getAtomicTypes() as $elementAtomic) {
                        $allElementTypes[] = self::normalizeLiteral($elementAtomic);
                    }
                } else {
                    $allElementTypes[] = Type::getMixed()->getSingleAtomic();
                }
            }
        }
        if ($allElementTypes === []) {
            return null;
        }
        return new Union([new TGenericObject(FlattenSequence::class, [new Union($allElementTypes)])]);
    }

    private static function getValueType(Atomic $atomic): ?Union
    {
        if ($atomic instanceof TGenericObject) {
            return array_slice($atomic->type_params, -1)[0] ?? null;
        }
        if ($atomic instanceof TArray) {
            return $atomic->type_params[1] ?? null;
        }
        if ($atomic instanceof TKeyedArray) {
            return $atomic->getGenericValueType();
        }
        if ($atomic instanceof TIterable) {
            return $atomic->type_params[1] ?? null;
        }
        return null;
    }

    private static function normalizeLiteral(Atomic $atomic): Atomic
    {
        if ($atomic instanceof TLiteralInt) {
            return Type::getInt()->getSingleAtomic();
        }
        if ($atomic instanceof TLiteralFloat) {
            return Type::getFloat()->getSingleAtomic();
        }
        if ($atomic instanceof TLiteralString) {
            return Type::getString()->getSingleAtomic();
        }
        if ($atomic instanceof TBool) {
            return Type::getBool()->getSingleAtomic();
        }
        return $atomic;
    }
}
