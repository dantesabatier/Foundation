<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\BlockOperation;

final class BlockOperationTest extends TestCase
{
    public function testConstructorAddsTheInitialExecutionBlock(): void
    {
        $operation = new BlockOperation(function (): void {
        });

        $this->assertSame(1, $operation->executionBlocks->count);
    }

    public function testMainRunsExecutionBlocksInInsertionOrder(): void
    {
        $order = [];
        $operation = new BlockOperation(function () use (&$order): void {
            $order[] = "first";
        });
        $operation->addExecutionBlock(function () use (&$order): void {
            $order[] = "second";
        });
        $operation->addExecutionBlock(function () use (&$order): void {
            $order[] = "third";
        });

        $operation->main();

        $this->assertSame(["first", "second", "third"], $order);
    }

    public function testMainSkipsEveryBlockWhenAlreadyCancelled(): void
    {
        $ran = false;
        $operation = new BlockOperation(function () use (&$ran): void {
            $ran = true;
        });
        $operation->cancel();

        $operation->main();

        $this->assertFalse($ran);
    }

    public function testCancellationStopsRemainingBlocks(): void
    {
        $order = [];
        $operation = null;
        $operation = new BlockOperation(function () use (&$operation, &$order): void {
            assert($operation instanceof BlockOperation);
            $order[] = "first";
            $operation->cancel();
        });
        $operation->addExecutionBlock(function () use (&$order): void {
            $order[] = "second";
        });

        $operation->main();

        $this->assertSame(["first"], $order);
    }
}
