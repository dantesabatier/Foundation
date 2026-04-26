<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

enum FilteringMethod: int
{
    case default = 0;
    case useKey = 1;
    case useValue = 2;
}
