<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 28/06/20
 * Time: 20:08
 */

namespace Sabatier\Foundation;

enum KeyValueChange: int
{
    case setting = 0;
    case insertion = 1;
    case removal = 2;
    case replacement = 3;
}
