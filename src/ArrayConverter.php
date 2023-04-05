<?php

namespace Sabatier\Foundation;

/** @internal */
readonly class ArrayConverter
{
    private function __construct(private array $array)
    {
    }

    public static function arrayWithArray(array $array): ArrayClass
    {
        return (new ArrayConverter($array))->array();
    }

    public static function dictionaryWithArray(array $array): Dictionary
    {
        return (new ArrayConverter($array))->dictionary();
    }

    private function newArray(array $array): ArrayClass
    {
        /** @var ArrayClass $arrayClass */
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
        /** @var Dictionary $dictionary */
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
        return $this->newArray($this->array);
    }

    private function dictionary(): Dictionary
    {
        if (!is_sequential($this->array)) {
            return $this->newDictionary($this->array);
        }
        return $this->newDictionary(array_combine(array_map(fn(int $i): string => human_readable_value($i), array_keys($this->array)), array_values($this->array)));
    }
}
