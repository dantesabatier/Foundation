<?php

namespace Sabatier\Foundation\Test;

use Sabatier\Foundation\Date;
use Sabatier\Foundation\ObjectClass;

class Base extends ObjectClass
{
    public readonly Date $creationDate;

    public function __construct()
    {
        $this->creationDate = new Date();
    }
}