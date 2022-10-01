<?php

namespace Sabatier\Foundation\Test;

class Money extends Base
{
    public function __construct(public float $value)
    {
        parent::__construct();
    }
}