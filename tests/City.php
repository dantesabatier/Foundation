<?php

namespace Sabatier\Foundation\Test;

class City extends Base
{
    public function __construct(public readonly string $name)
    {
        parent::__construct();
    }
}