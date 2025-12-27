<?php

namespace Sabatier\Foundation;

enum CollectionDifferenceChangeType
{
    case insert;
    case remove;
    case move;
}
