<?php

namespace Sabatier\Foundation\Test;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Data;

class DataTest extends TestCase
{
    public function testCanBeCreatedFromString(): void
    {
        self::assertInstanceOf(
            Data::class,
            new Data('Hello world!')
        );
    }
}
