<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Foundation\Plugins;

use Override;
use Psalm\Internal\MethodIdentifier;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type;
use Psalm\Type\Atomic\TClosure;
use Psalm\Type\Atomic\TGenericObject;
use Psalm\Type\Atomic\TNull;
use Psalm\Type\Union;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CollectionDifference;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\IndexPath;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\Slice;

final class CompactMapReturnTypeProvider implements MethodReturnTypeProviderInterface
{
    #[Override]
    public static function getClassLikeNames(): array
    {
        return [ArrayClass::class, CollectionDifference::class, Dictionary::class, IndexPath::class, Set::class, Slice::class];
    }

    #[Override]
    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Union
    {
        if ($event->getMethodNameLowercase() !== "compactmap") {
            return null;
        }
        $args = $event->getCallArgs();
        if (!isset($args[0])) {
            return null;
        }
        $source = $event->getSource();
        $argType = $source->getNodeTypeProvider()->getType($args[0]->value);
        if (!$argType) {
            return null;
        }
        $closureReturnType = null;
        foreach ($argType->getAtomicTypes() as $atomic) {
            if ($atomic instanceof TClosure && $atomic->return_type) {
                $closureReturnType = $atomic->return_type;
                break;
            }
        }
        if (!$closureReturnType) {
            return null;
        }
        $filtered = [];
        foreach ($closureReturnType->getAtomicTypes() as $atomic) {
            if (!$atomic instanceof TNull) {
                $filtered[] = $atomic;
            }
        }
        if (!$filtered) {
            return Type::getNever();
        }
        $cleanResult = new Union($filtered);
        $codebase = $event->getSource()->getCodebase();
        $methodID = new MethodIdentifier($event->getFqClasslikeName(), $event->getMethodNameLowercase());
        $methodStorage = $codebase->methods->getStorage($methodID);
        if (!($returnType = $methodStorage->return_type)) {
            return null;
        }
        $newAtomics = [];
        foreach ($returnType->getAtomicTypes() as $atomic) {
            if (!$atomic instanceof TGenericObject) {
                $newAtomics[] = $atomic;
                continue;
            }
            $params = $atomic->type_params;
            array_pop($params);
            $params[] = $cleanResult;
            $newAtomics[] = new TGenericObject($atomic->value, $params);
        }
        if ($newAtomics === []) {
            return null;
        }
        return new Union($newAtomics);
    }
}

