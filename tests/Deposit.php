<?php

namespace Sabatier\Foundation\Test;

class Deposit extends Base
{
    public function __construct(public Money $amount)
    {
        parent::__construct();
    }
}