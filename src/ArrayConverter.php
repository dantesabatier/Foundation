<?php

namespace Sabatier\Foundation;

/**
 * @template T
 * @internal
 */
readonly class ArrayConverter
{
    /** @var ArrayClass<T> */
    public ArrayClass $array;
    /** @var Dictionary<T> */
    public Dictionary $dictionary;

    /** @var array<array-key, T> $reserved */
    public function __construct(private array $reserved)
    {
        unset($this->array);
        unset($this->dictionary);
    }

    public function __get(string $name)
    {
        $this->$name = match ($name) {
            "array" => $this->array(),
            "dictionary" => $this->dictionary(),
            default => throw new UndefinedKeyException()
        };
    }

    /** @deprecated */
    public static function arrayWithArray(array $array): ArrayClass
    {
        return (new ArrayConverter($array))->array;
    }

    /** @deprecated */
    public static function dictionaryWithArray(array $array): Dictionary
    {
        return (new ArrayConverter($array))->dictionary;
    }

    private function newArray(array $array): ArrayClass
    {
        /** @var ArrayClass<mixed> $arrayClass */
        $arrayClass = new ArrayClass();
        foreach ($array as $element) {
            if (is_array($element)) {
                $arrayClass[] = is_sequential($element) ? $this->newArray($element) : $this->newDictionary($element);
            } else {
                $arrayClass[] = $element ?? Nil::nil();
            }
        }
        return $arrayClass;
    }

    private function newDictionary(array $array): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $dictionary[$key] = is_sequential($value) ? $this->newArray($value) : $this->newDictionary($value);
            } else {
                $dictionary[$key] = $value ?? Nil::nil();
            }
        }
        return $dictionary;
    }

    private function array(): ArrayClass
    {
        return $this->newArray($this->reserved);
    }

    private function dictionary(): Dictionary
    {
        if (!is_sequential($this->reserved)) {
            return $this->newDictionary($this->reserved);
        }
        return $this->newDictionary(array_combine(array_map(fn(int $i): string => human_readable_value($i), array_keys($this->reserved)), array_values($this->reserved)));
    }
}
