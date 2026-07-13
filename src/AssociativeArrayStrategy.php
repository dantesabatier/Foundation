<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Override;

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
    public function convert(array $array, bool $preserveNull = true): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $dictionary[$key] = is_sequential($value) ? $this->nestedConversionStrategy->convert($value, $preserveNull) : $this->convert($value, $preserveNull);
            } else {
                if ($value === null && $preserveNull) {
                    $value = Nil::nil();
                }
                $dictionary[$key] = $value;
            }
        }
        return $dictionary;
    }
}
