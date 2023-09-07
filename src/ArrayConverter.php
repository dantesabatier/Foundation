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

    /**
     * @param array<array-key, T> $reserved
     */
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

    /**
     * @param array<T> $array
     * @return ArrayClass<T>
     */
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

    /**
     * @param array<T> $array
     * @return Dictionary<T>
     */
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

    /**
     * @return ArrayClass<T>
     */
    private function array(): ArrayClass
    {
        return $this->newArray($this->reserved);
    }

    /**
     * @return Dictionary<T>
     */

    private function dictionary(): Dictionary
    {
        if (!is_sequential($this->reserved)) {
            return $this->newDictionary($this->reserved);
        }
        return $this->newDictionary(array_combine(array_map(fn(int $i): string => human_readable_value($i), array_keys($this->reserved)), array_values($this->reserved)));
    }
}
