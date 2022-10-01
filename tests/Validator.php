<?php

namespace Sabatier\Foundation\Test;

class Validator
{

    public function validate(string $hash): bool
    {
        return $hash > 0;
    }
}