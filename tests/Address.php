<?php

namespace Sabatier\Foundation\Test;

class Address extends Base
{
    public function __construct(public string $street, public City $city)
    {
        parent::__construct();
    }
}