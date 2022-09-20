<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

trait Debuggable
{
    public static function className(): string
    {
        return class_name(static::class);
    }

    public function description(): string
    {
        return sprintf('<%s %s>', static::class, spl_object_id($this));
    }

    public function debugDescription(): string
    {
        return sprintf('<%s %s>', static::class, spl_object_id($this));
    }
}
