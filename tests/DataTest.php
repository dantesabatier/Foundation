<?php

namespace Sabatier\Foundation\Test;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Data;

class DataTest extends TestCase
{
    public function testCanBeCreatedFromString(): void
    {
        $data = new Data('Hello world!');
        self::assertInstanceOf(
            Data::class,
            $data
        );
        error_log($data->description());
    }
}
