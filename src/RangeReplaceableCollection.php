<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 19/07/20
 * Time: 09:58
 */

namespace Sabatier\Foundation;

/**
 * Interface RangeReplaceableCollection
 * A collection that supports replacement of an arbitrary subrange of elements with the elements of another collection.
 * @package Sabatier\Foundation
 * @template Element
 * @template-extends MutableCollection<int, Element>
 */
interface RangeReplaceableCollection extends MutableCollection
{
    /**
     * Creates a new collection containing the specified number of a single, repeated value.
     * @param mixed $value The element to repeat.
     * @param int $count The number of times to repeat the value passed in the repeating parameter. count must be zero or greater.
     * @return RangeReplaceableCollection
     */
    public static function repeating(mixed $value, int $count): self;

    /**
     * Inserts the value into the collection at the specified position.
     * The new element is inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new element is appended to the collection.
     * @param Element $element The new element to insert into the collection.
     * @param int $at The position at which to insert the new element. index must be a valid index into the collection.
     */
    public function insert(mixed $element, int $at): void;

    /**
     * Inserts the elements of a sequence into the collection at the specified position.
     * The new elements are inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new elements are appended to the collection.
     * @param iterable<int, Element> $newElements The new elements to insert into the collection.
     * @param int $at The position at which to insert the new elements. index must be a valid index of the collection.
     */
    public function insertContentsOf(iterable $newElements, int $at = NotFound): void;

    /**
     * @param int $index The index of the member to remove, position must be a valid index of the collection, and must not be equal to the collection's end index.
     * @return Element The value that was removed.
     */
    public function removeAt(int $index);

    /**
     * This method has the effect of removing the specified range of elements from the collection and inserting the new elements at the same location.
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
