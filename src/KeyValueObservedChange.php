<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 03/07/20
 * Time: 13:24
 */

namespace Sabatier\Foundation;

class KeyValueObservedChange
{
    public KeyValueChange $kind = KeyValueChange::setting;
    public mixed $newValue = null;
    public mixed $oldValue = null;
    public bool $isPrior = false;
}
