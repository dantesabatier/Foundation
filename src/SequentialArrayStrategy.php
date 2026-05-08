<?php

namespace Sabatier\Foundation;

use Override;

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
    public function convert(array $array, bool $preserveNull = true): ArrayClass
    {
        /** @var ArrayClass<mixed> $arrayClass */
        $arrayClass = new ArrayClass();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $arrayClass[$key] = is_sequential($value) ? $this->convert($value, $preserveNull) : $this->nestedConversionStrategy->convert($value, $preserveNull);
            } else {
                if ($value === null && $preserveNull) {
                    $value = Nil::nil();
                }
                $arrayClass[$key] = $value;
            }
        }
        return $arrayClass;
    }
}
