<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\OptionSet;

final class OptionSetTest extends TestCase
{
    public function testEmptySetContainsOnlyTheEmptyMask(): void
    {
        $options = new OptionSet();

        $this->assertSame(0, $options->rawValue);
        $this->assertTrue($options->contains(0));
        $this->assertFalse($options->contains(1));
    }

    public function testConstructedMaskContainsItsIndividualAndCombinedOptions(): void
    {
        $options = new OptionSet(1 | 4);

        $this->assertTrue($options->contains(1));
        $this->assertTrue($options->contains(4));
        $this->assertTrue($options->contains(1 | 4));
        $this->assertFalse($options->contains(2));
        $this->assertFalse($options->contains(1 | 2));
    }

    public function testInsertAccumulatesOptions(): void
    {
        $options = new OptionSet(1);

        $options->insert(4);
        $options->insert(4);

        $this->assertSame(5, $options->rawValue);
        $this->assertTrue($options->contains(1 | 4));
    }

    public function testRemoveClearsOnlyTheRequestedOptions(): void
    {
        $options = new OptionSet(1 | 2 | 4);

        $options->remove(1 | 4);

        $this->assertSame(2, $options->rawValue);
        $this->assertFalse($options->contains(1));
        $this->assertTrue($options->contains(2));
        $this->assertFalse($options->contains(4));
    }
}
