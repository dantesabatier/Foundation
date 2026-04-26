<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 19/07/20
 * Time: 09:58
 */
namespace Sabatier\Foundation;

/**
 * A collection that supports replacement of an arbitrary subrange of elements with the elements of another collection.
 * @template Element
 * @template-extends MutableCollection<int, Element>
 */
interface RangeReplaceableCollection extends MutableCollection
{
    /**
     * Creates a new collection containing the specified number of a single, repeated `$value`.
     * @param mixed $value The element to repeat.
     * @param int $count The number of times to repeat the value passed in the repeating parameter. `$count` must be zero or greater.
     */
    public static function repeating(mixed $value, int $count): self;

    /**
     * This method has the effect of removing the specified range of elements from the collection and inserting the new elements at the same location.
     * 
     * The number of new elements need not match the number of elements being removed.
     * @param Range $subrange The subrange of the collection to replace. The start and end of a subrange must be valid indices of the collection.
     * @param Collection<int, Element> $newElements The new elements to add to the collection.
     */
    public function replaceSubrange(Range $subrange, Collection $newElements): void;

    /**
     * Removes the elements in the specified subrange from the collection.
     * @param Range $subrange The range of the collection to be removed. The bounds of the range must be valid indices of the collection.
     */
    public function removeSubrange(Range $subrange): void;
}
