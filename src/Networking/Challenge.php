<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\array_first;
use function Sabatier\Foundation\string_has_suffix;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

/** @internal */
class Challenge
{
    /**
     * @param string $authScheme
     * @param ArrayClass<AuthParameter> $authParameters
     */
    public function __construct(public readonly string $authScheme, public readonly ArrayClass $authParameters)
    {
    }

    public function authenticationMethod(): ?string
    {
        return array_first(URLProtectionSpace::authenticationMethods, fn(string $authenticationMethod): bool => string_has_suffix($authenticationMethod, $this->authScheme, CompareOptions::caseInsensitive));
    }

    public function parameter(string $name): ?AuthParameter
    {
        return $this->authParameters->first(fn(AuthParameter $parameter): bool => string_is_equal($parameter->name, $name, CompareOptions::caseInsensitive));
    }

    /**
     * @param HTTPURLResponse $response
     * @return ArrayClass<Challenge>
     */
    public static function challenges(HTTPURLResponse $response): ArrayClass
    {
        if (!($authenticateValue = $response->valueForHttpHeaderField("WWW-Authenticate"))) {
            return new ArrayClass();
        }
        return self::challengesFromAuthenticateFieldValue($authenticateValue);
    }

    /**
     * @param string $authenticateView
     * @return ArrayClass<Challenge>
     */
    private static function challengesFromAuthenticateFieldValue(string $authenticateView): ArrayClass
    {
        /** @var ArrayClass<Challenge> $challenges */
        $challenges = new ArrayClass();
        while ($authenticateView !== "") {
            if (!($index = strpos($authenticateView, " "))) {
                break;
            }
            $authScheme = substring_to_index($authenticateView, $index);
            $authDataView = substring_from_index($authenticateView, $index);
            $authParameters = AuthParameter::parameters($authDataView);
            $challenge = new Challenge($authScheme, $authParameters);
            if ($challenge->parameter("realm") !== null) {
                $challenges->append($challenge);
            }
            if (!($commaIndex = strpos($authenticateView, ","))) {
                break;
            }
            $authenticateView = trim(substring_from_index($authenticateView, $commaIndex));
        }
        return $challenges;
    }
}
