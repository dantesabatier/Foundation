<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;

/** @internal */
readonly class AuthParameter
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
        /** @var ArrayClass<AuthParameter> */
        return (new ArrayClass(explode(",", $parametersView)))->compactMap(function (string $e): ?AuthParameter {
            /** @psalm-suppress TypeDoesNotContainType */
            if (/** @phpstan-ignore-line */!($components = array_map(fn(string $e): string => trim($e), explode("=", $e))) || count($components) !== 2) {
                return null;
            }
            [$name, $value] = $components;
            return new AuthParameter($name, $value);
        });
    }
}
