<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Scanner;
use function Sabatier\Foundation\string_has_suffix;

/** @internal */
final class Challenge
{
    public ?string $authenticationMethod {
        get => array_find(URLProtectionSpace::authenticationMethods, fn(string $authenticationMethod): bool => string_has_suffix($authenticationMethod, $this->authScheme, CompareOptions::caseInsensitive));
    }

    /**
     * @param string $authScheme
     * @param Dictionary<string> $authParameters
     */
    public function __construct(public string $authScheme, public Dictionary $authParameters)
    {
    }

    public function parameter(string $name): ?string
    {
        return $this->authParameters->valueForCaseInsensitiveKey($name);
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
    public static function challengesFromAuthenticateFieldValue(string $authenticateView): ArrayClass
    {
        /** @var ArrayClass<Challenge> $challenges */
        $challenges = new ArrayClass();
        $scanner = new Scanner($authenticateView);
        while (!$scanner->isAtEnd) {
            $scanner->scanCharacters(",");
            $authScheme = "";
            if (!$scanner->scanUpCharacters(" \t,=", $authScheme)) {
                break;
            }
            /** @var Dictionary<string> $authParameters */
            $authParameters = new Dictionary();
            while (true) {
                $location = $scanner->scanLocation;
                $name = "";
                if (!$scanner->scanUpCharacters(" \t,=", $name) || !$scanner->scanString("=")) {
                    $scanner->scanLocation = $location;
                    break;
                }
                if (($value = self::parameterValue($scanner)) === null) {
                    return $challenges;
                }
                $authParameters[$name] = $value;
                if (!$scanner->scanString(",")) {
                    break;
                }
            }
            $challenge = new Challenge($authScheme, $authParameters);
            if ($challenge->parameter("realm") !== null) {
                $challenges->append($challenge);
            }
        }
        return $challenges;
    }

    private static function parameterValue(Scanner $scanner): ?string
    {
        if (!$scanner->scanString("\"")) {
            return $scanner->scanUpCharacters(" \t,", $value) ? $value : null;
        }
        $skipped = $scanner->charactersToBeSkipped;
        $scanner->charactersToBeSkipped = "";
        $value = "";
        while (true) {
            $part = "";
            if ($scanner->scanUpCharacters("\\\"", $part)) {
                /** @var string $part */
                $value .= $part;
            }
            if ($scanner->scanString("\"")) {
                $scanner->charactersToBeSkipped = $skipped;
                return $value;
            }
            if (!$scanner->scanString("\\") || $scanner->isAtEnd) {
                break;
            }
            $value .= mb_substr($scanner->string, $scanner->scanLocation, 1);
            $scanner->scanLocation += 1;
        }
        $scanner->charactersToBeSkipped = $skipped;
        return null;
    }
}
