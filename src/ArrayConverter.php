<?php

namespace Sabatier\Foundation;

/**
 * @internal
 */
class ArrayConverter
{
    public ArrayClass $array {
        get => $this->newCollection($this->reserved, ArrayClass::class);
    }
    public Dictionary $dictionary {
        get => $this->newCollection(is_sequential($this->reserved) ? array_combine(array_map(fn(int $i): string => human_readable_value($i), array_keys($this->reserved)), array_values($this->reserved)) : $this->reserved, Dictionary::class);
    }

    /**
     * @param array<array-key, mixed> $reserved
     */
    public function __construct(private readonly array $reserved)
    {
    }

    /**
     * @param array $array
     * @param class-string<ArrayClass|Dictionary> $class
     * @return mixed
     */
    private function newCollection(array $array, string $class): mixed
    {
        /** @psalm-suppress UnsafeInstantiation */
        $collection = new $class();
        foreach ($array as $i => $e) {
            if (is_array($e)) {
                $collection[$i] = $this->newCollection($e, is_sequential($e) ? ArrayClass::class : Dictionary::class);
            } else {
                $collection[$i] = $e ?? Nil::nil();
            }
        }
        return $collection;
    }
}
