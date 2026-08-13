<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Override;
use stdClass;

/** @internal */
final class SequentialArrayStrategy implements ArrayConversionStrategy
{
    public ArrayConversionStrategy $nestedConversionStrategy {
        get => AssociativeArrayStrategy::instance();
    }
    private static SequentialArrayStrategy $instance;

    public static function instance(): SequentialArrayStrategy
    {
        return self::$instance ??= new SequentialArrayStrategy();
    }

    #[Override]
    public function convert(array|stdClass $value, bool $preserveNull = true): ArrayClass
    {
        /** @var ArrayClass<mixed> $arrayClass */
        $arrayClass = new ArrayClass();
        foreach ($value instanceof stdClass ? get_object_vars($value) : $value as $key => $element) {
            if (is_array($element) || $element instanceof stdClass) {
                // A stdClass becomes a Dictionary even when empty: that is what separates it from a
                // list, and is_sequential() answers true for every empty array.
                $arrayClass[$key] = $element instanceof stdClass || !is_sequential($element) ? $this->nestedConversionStrategy->convert($element, $preserveNull) : $this->convert($element, $preserveNull);
            } else {
                if ($element === null && $preserveNull) {
                    $element = Nil::nil();
                }
                $arrayClass[$key] = $element;
            }
        }
        return $arrayClass;
    }
}
