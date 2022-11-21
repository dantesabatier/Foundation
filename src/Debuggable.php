<?php

namespace Sabatier\Foundation;

trait Debuggable
{
    public static function className(): string
    {
        return class_name(static::class);
    }

    public function description(): string
    {
        return sprintf('<%s %s>', static::className(), spl_object_id($this));
    }

    public function debugDescription(): string
    {
        return sprintf('<%s %s>', static::className(), spl_object_id($this));
    }
}
