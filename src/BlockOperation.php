<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 10/06/20
 * Time: 01:18
 */

namespace Sabatier\Foundation;

use Closure;

/**
 * Class BlockOperation
 * An operation that manages the concurrent execution of one or more blocks.
 * @package Sabatier\Foundation
 */
class BlockOperation extends Operation
{
    /** @var ArrayClass<Closure(): void> The blocks associated with the receiver. */
    public readonly ArrayClass $executionBlocks;

    /**
     * Creates and returns an NSBlockOperation object and adds the specified block to it.
     * @param Closure(): void $block The block to add to the new block operation object's list.
     * The block should take no parameters and have no return value.
     */
    public function __construct(Closure $block)
    {
        parent::__construct();
        $this->executionBlocks = new ArrayClass([$block]);
    }

    public function main(): void
    {
        foreach ($this->executionBlocks as $executionBlock) {
            if ($this->isCancelled) {
                break;
            }
            $executionBlock();
        }
    }

    /**
     * Adds the specified block to the receiver's list of blocks to perform.
     * @param Closure(): void $block
     */
    public function addExecutionBlock(Closure $block): void
    {
        $this->executionBlocks->append($block);
    }
}
