<?php

namespace Sabatier\Foundation;

enum CollectionDifferenceChangeType: int
{
    case insert = 0;
    case remove = 1;
    case move = 2;
}
