<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\Deprecated;

trait Debuggable
{
    #[Deprecated]
    public static function className(): string
    {
        return class_name(static::class);
    }

    public function description(): string
    {
        return sprintf("<%s %s>", static::class, spl_object_id($this));
    }

    public function debugDescription(): string
    {
        return sprintf("<%s %s>", static::class, spl_object_id($this));
    }
}
