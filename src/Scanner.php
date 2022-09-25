<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

/**
 * Class Scanner
 * A string parser that scans for substrings or characters in a character set, and for numeric values from decimal, hexadecimal, and floating-point representations.
 * @package Sabatier\Foundation
 * @property int $scanLocation The character position at which the receiver will begin its next scanning operation. This property is useful for backing up to rescan after an error. Rather than setting the scan location directly to skip known sequences of characters, use {@see scanString()} or {@see scanCharacters()}, which allow you to verify that the expected substring (or set of characters) is in fact present.
 * @property-read bool $isAtEnd Flag that indicates whether the receiver has exhausted all significant characters.
 */
class Scanner extends ObjectClass
{
    protected int $scanLocation = 0;
    /** @var bool Flag that indicates whether the receiver distinguishes case in the characters it scans. */
    public bool $caseSensitive = false;
    /** @var string Character set containing the characters the scanner ignores when looking for a scannable element. Characters to be skipped are skipped prior to the scanner examining the target. For example, if a scanner ignores spaces, and you send it a {@see scanInt()} message, it skips spaces until it finds a decimal digit or other character. While an element is being scanned, no characters are skipped. If you scan for something made of characters in the set to be skipped (for example, using {@see scanInt()} when the set of characters to be skipped is the decimal digits), the result is undefined. The characters to be skipped are treated as single values. A scanner doesn't apply its case sensitivity setting to these characters and doesn't attempt to match composed character sequences with anything in the set of characters to be skipped (though it does match pre-composed characters individually). If you want to skip all vowels while scanning a string, for example, you can set the characters to be skipped to those in the string “AEIOUaeiou” (plus any accented variants with pre-composed characters). The default set to skip is the whitespace and newline character set.
     */
    public string $charactersToBeSkipped = ' ';

    /**
     * Returns a Scanner object initialized to scan a given string.
     * @param string $string The string to scan.
     */
    public function __construct(public readonly string $string)
    {
    }

    public function __get(string $name)
    {
        return match ($name) {
            'scanLocation' => $this->$name,
            'isAtEnd' => $this->scanLocation >= strlen($this->string),
            default => $this->valueForUndefinedKey($name),
        };
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name == 'scanLocation') {
            $this->$name = min(max($value, 0), strlen($this->string));
        } else {
            $this->setValueForUndefinedKey($value, $name);
        }
    }

    private function scanSet(string $characters, ?string &$into = null, bool $stop = false): bool
    {
        if ($this->isAtEnd) {
            return false;
        }
        $substring = null;
        $scanLocation = $this->scanLocation;
        $length = strlen($this->string);
        while ($scanLocation != $length) {
            $character = $this->string[$scanLocation];
            if (in_string($characters, $character) === $stop) {
                break;
            }
            if (!in_string($this->charactersToBeSkipped, $character)) {
                if (!$substring) {
                    $substring = '';
                }
                $substring .= $character;
            }
            $scanLocation += 1;
        }
        if ($substring !== null) {
            $this->scanLocation = $scanLocation;
        }
        $into = $substring;
        return true;
    }

    /**
     * Scans the string as long as characters from a given character set are encountered, accumulating characters into a string that's returned by reference.
     * @param string $characters The set of characters to scan.
     * @param string|null $into Upon return, contains the characters scanned.
     * @return bool true if the receiver scanned any characters, otherwise false.
     */
    public function scanCharacters(string $characters, ?string &$into = null): bool
    {
        return $this->scanSet($characters, $into);
    }

    /**
     * Scans the string until a character from a given character set is encountered, accumulating characters into a string that's returned by reference.
     * @param string $characters The set of characters up to which to scan.
     * @param string|null $into Upon return, contains the characters scanned.
     * @return bool true if the receiver scanned any characters, otherwise false.
     * If the only scanned characters are in the {@see charactersToBeSkipped} character set (which is the whitespace and newline character set by default), then returns false.
     */
    public function scanUpCharacters(string $characters, ?string &$into = null): bool
    {
        return $this->scanSet($characters, $into, true);
    }

    /**
     * Scans a given string, returning an equivalent string object by reference if a match is found.
     * If string is present at the current scan location, then the current scan location is advanced to after the string; otherwise the scan location does not change.
     * @param string $string The string for which to scan at the current scan location.
     * @param string|null $into Upon return, if the receiver contains a string equivalent to string at the current scan location, contains a string equivalent to string.
     * @return bool true if string matches the characters at the scan location, otherwise false.
     */
    public function scanString(string $string, ?string &$into = null): bool
    {
        $this->skipCharacters();
        if ($this->isAtEnd) {
            return false;
        }
        $substring = substr($this->string, $this->scanLocation, strlen($string));
        if ((!string_is_equal($substring, $string, $this->caseSensitive ? CompareOptions::none : CompareOptions::caseInsensitive))) {
            return false;
        }
        $this->scanLocation = $this->scanLocation + strlen($substring);
        $into = $substring;
        return true;
    }

    /**
     * Scans the string until a given string is encountered, accumulating characters into a string that's returned by reference.
     * @param string $string The string to scan up to.
     * @param string|null $into Upon return, contains any characters that were scanned.
     * @return bool true if the receiver scans any characters, otherwise false.
     * If the only scanned characters are in the {@see charactersToBeSkipped} character set (which by default is the whitespace and newline character set), then this method returns false.
     */
    public function scanUpString(string $string, ?string &$into = null): bool
    {
        $this->skipCharacters();
        if ($this->isAtEnd) {
            return false;
        }
        $substring = substring_from_index($this->string, $this->scanLocation);
        $location = $this->caseSensitive ? strpos($substring, $string) : stripos($substring, $string);
        if ($location === false) {
            return false;
        }
        $substring = substr($this->string, $this->scanLocation, $location);
        $this->scanLocation = $this->scanLocation + strlen($substring);
        $into = $substring;
        return true;
    }

    /**
     * @param-out mixed $number
     * @param mixed $number
     * @param bool $isInt
     * @return bool
     */
    private function scanNumber(mixed &$number, bool $isInt = true): bool
    {
        $this->skipCharacters();
        if ($this->isAtEnd) {
            return false;
        }
        $matches = [];
        $substring = substring_from_index($this->string, $this->scanLocation);
        if (!string_search($substring, $isInt ? "[1-9]+[0-9]*" : "[0-9]*\.?[0-9]+([eE][0-9]+)?", SearchMethod::beginsWith, CompareOptions::quoted, $matches)) {
            return false;
        }
        if (empty($matches)) {
            return false;
        }
        $value = $matches[0];
        $number = filter_var($value, $isInt ? FILTER_VALIDATE_INT : FILTER_VALIDATE_FLOAT);
        $this->scanLocation = (int)strpos($this->string, $value, $this->scanLocation) + strlen($value);
        return true;
    }

    /**
     * Scans for an int value from a decimal representation, returning a found value by reference.
     * @param int $int Upon return, contains the scanned value.
     * @return bool true if the receiver finds a valid decimal integer representation, otherwise false.
     */
    public function scanInt(int &$int): bool
    {
        return $this->scanNumber($int);
    }

    /**
     * Scans for a float value, returning a found value by reference.
     * @param float $float Upon return, contains the scanned value.
     * @return bool true if the receiver finds a valid floating-point representation, otherwise false.
     */
    public function scanFloat(float &$float): bool
    {
        return $this->scanNumber($float, false);
    }

    private function skipCharacters(): void
    {
        $scanLocation = $this->scanLocation;
        while ($scanLocation < strlen($this->string)) {
            if (!in_string($this->charactersToBeSkipped, $this->string[$scanLocation], $this->caseSensitive ? CompareOptions::none : CompareOptions::caseInsensitive)) {
                break;
            }
            $scanLocation++;
        }
        $this->scanLocation = $scanLocation;
    }
}
