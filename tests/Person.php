<?php

namespace Sabatier\Foundation\Test;

use Sabatier\Foundation\Set;

class Person extends Base
{
    public string $name;
    public int $age;
    /** @var Set<Deposit> */
    public Set $deposits;
    public Money $savings;
    /** @var Set<Address> */
    public Set $addresses;
}