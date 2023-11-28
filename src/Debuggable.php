<?php

namespace Sabatier\Foundation;

trait Debuggable
{
    public function description(): string
    {
        return sprintf("<%s %s>", class_name(static::class), spl_object_id($this));
    }

    public function debugDescription(): string
    {
        return sprintf("<%s %s>", class_name(static::class), spl_object_id($this));
    }
}
