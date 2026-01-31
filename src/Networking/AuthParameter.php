<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;

/** @internal */
final readonly class AuthParameter
{
    public function __construct(public string $name, public string $value)
    {
    }

    /**
     * @param string $parametersView
     * @return ArrayClass<AuthParameter>
     */
    public static function parameters(string $parametersView): ArrayClass
    {
        return new ArrayClass(explode(",", $parametersView))->compactMap(function (string $e): ?AuthParameter {
            $components = array_map(trim(...), explode("=", $e));
            if (count($components) !== 2) {
                return null;
            }
            [$name, $value] = $components;
            return new AuthParameter($name, $value);
        });
    }
}
