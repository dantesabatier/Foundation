<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Override;
use stdClass;

/** @internal */
final  class AssociativeArrayStrategy implements ArrayConversionStrategy
{
    public ArrayConversionStrategy $nestedConversionStrategy {
        get => SequentialArrayStrategy::instance();
    }
    private static AssociativeArrayStrategy $instance;

    public static function instance(): AssociativeArrayStrategy
    {
        return self::$instance ??= new AssociativeArrayStrategy();
    }

    #[Override]
    public function convert(array|stdClass $value, bool $preserveNull = true): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        foreach ($value instanceof stdClass ? get_object_vars($value) : $value as $key => $element) {
            if (is_array($element) || $element instanceof stdClass) {
                // A stdClass becomes a Dictionary even when empty: that is what separates it from a
                // list, and is_sequential() answers true for every empty array.
                $dictionary[$key] = $element instanceof stdClass || !is_sequential($element) ? $this->convert($element, $preserveNull) : $this->nestedConversionStrategy->convert($element, $preserveNull);
            } else {
                if ($element === null && $preserveNull) {
                    $element = Nil::nil();
                }
                $dictionary[$key] = $element;
            }
        }
        return $dictionary;
    }
}
