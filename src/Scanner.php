<?php

namespace Sabatier\Foundation;

/**
 * A string parser that scans for substrings or characters in a character set, and for numeric values from decimal, hexadecimal, and floating-point representations.
 */
class Scanner extends ObjectClass
{
    /** @var int The character position at which the receiver will begin its next scanning operation. This property is useful for backing up to rescan after an error. Rather than setting the scan location directly to skip known sequences of characters, use {@see scanString()} or {@see scanCharacters()}, which allow you to verify that the expected substring (or set of characters) is in fact present. */
    public int $scanLocation = 0 {
        set => min(max($value, 0), $this->length);
    }
    /** @var bool Flag that indicates whether the receiver distinguishes the case in the characters it scans. */
    public bool $caseSensitive = false;
    /** @var string Character set containing the characters the scanner ignores when looking for a scannable element. Characters to be skipped are skipped prior to the scanner examining the target. For example, if a scanner ignores spaces, and you send it a {@see scanInt()} message, it skips spaces until it finds a decimal digit or other character. While an element is being scanned, no characters are skipped. If you scan for something made of characters in the set to be skipped (for example, using {@see scanInt()} when the set of characters to be skipped is the decimal digits), the result is undefined. The characters to be skipped are treated as single values. A scanner doesn't apply its case sensitivity setting to these characters and doesn't attempt to match composed character sequences with anything in the set of characters to be skipped (though it does match pre-composed characters individually). If you want to skip all vowels while scanning a string, for example, you can set the characters to be skipped to those in the string "AEIOUaeiou" (plus any accented variants with pre-composed characters). The default set to skip is the whitespace and newline character set.
     */
    public string $charactersToBeSkipped = " \n\r\t";
    /** @var bool Flag that indicates whether the receiver has exhausted all significant characters. */
    public bool $isAtEnd {
        get => $this->scanLocation >= $this->length;
    }
    /** @var int Cached length of the string to avoid O(n) calculation in the property hook. */
    private int $length;

    /** @var array<string> Optimization: Array of characters for O(1) access inside loops. */
    private array $chars;

    /**
     * Returns a Scanner object initialized to scan a given string.
     * @param string $string The string to scan.
     */
    public function __construct(public readonly string $string)
    {
        $this->length = mb_strlen($string);
        $this->chars = mb_str_split($string);
    }

    private function scanSet(string $characters, ?string &$into = null, bool $stop = false): bool
    {
        $this->skipCharacters();
        if ($this->isAtEnd) {
            return false;
        }
        $scanLocation = $this->scanLocation;
        $startLocation = $scanLocation;
        while ($scanLocation < $this->length) {
            $character = $this->chars[$scanLocation];
            if (in_string($characters, $character) === $stop) {
                break;
            }
            $scanLocation++;
        }
        if ($scanLocation === $startLocation) {
            return false;
        }
        $into = mb_substr($this->string, $startLocation, $scanLocation - $startLocation);
        $this->scanLocation = $scanLocation;
        return true;
    }

    /**
     * Scans the string as long as characters from a given character set are encountered, accumulating characters into a string that reference returns.
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
     */
    public function scanUpCharacters(string $characters, ?string &$into = null): bool
    {
        return $this->scanSet($characters, $into, true);
    }

    /**
     * Scans a given string, returning an equivalent string object by reference if a match is found.
     *
     * If $string is present at the current scan location, then the current scan location is advanced to after the string; otherwise the scan location does not change.
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
        $length = mb_strlen($string);
        if (($this->scanLocation + $length) > $this->length) {
            return false;
        }
        $substring = mb_substr($this->string, $this->scanLocation, $length);
        if (!string_is_equal($substring, $string, $this->caseSensitive ? CompareOptions::none : CompareOptions::caseInsensitive)) {
            return false;
        }
        $this->scanLocation += $length;
        $into = $substring;
        return true;
    }

    /**
     * Scans the string until a given string is encountered, accumulating characters into a string that reference returns.
     * @param string $string The string to scan up to.
     * @param string|null $into Upon return, contains any characters that were scanned.
     * @return bool true if the receiver scans any characters, otherwise false.
     */
    public function scanUpString(string $string, ?string &$into = null): bool
    {
        $this->skipCharacters();
        if ($this->isAtEnd) {
            return false;
        }
        $substring = mb_substr($this->string, $this->scanLocation);
        $location = $this->caseSensitive ? mb_strpos($substring, $string) : mb_stripos($substring, $string);
        if ($location === false) {
            return false;
        }
        $into = mb_substr($substring, 0, $location);
        $this->scanLocation += $location;
        return true;
    }

    public function scanInt(int &$int): bool
    {
        return $this->scanNumber($int);
    }

    public function scanFloat(float &$float): bool
    {
        return $this->scanNumber($float, false);
    }

    /**
     * @param float|int $number
     * @param-out float|int $number
     * @param bool $isInt
     * @return bool
     */
    private function scanNumber(float|int &$number, bool $isInt = true): bool
    {
        $this->skipCharacters();
        if ($this->isAtEnd) {
            return false;
        }
        $substring = mb_substr($this->string, $this->scanLocation);
        $pattern = $isInt ? "[-+]?[0-9]+" : "[-+]?([0-9]*\.[0-9]+|[0-9]+)([eE][-+]?[0-9]+)?";
        if (!string_search($substring, $pattern, SearchMethod::beginsWith, CompareOptions::quoted, $matches)) {
            return false;
        }
        if (empty($matches)) {
            return false;
        }
        $value = $matches[0];
        $number = filter_var($value, $isInt ? FILTER_VALIDATE_INT : FILTER_VALIDATE_FLOAT);
        if ($number === false) {
            return false;
        }
        $this->scanLocation += mb_strlen((string)$value);
        return true;
    }

    /**
     * Scans for a hexadecimal int value (e.g., "0xFF", "A1").
     *
     * @param-out int $int
     * @return bool true if the receiver finds a valid hex int representation.
     */
    public function scanHexInt(int &$int): bool
    {
        $this->skipCharacters();
        if ($this->isAtEnd) {
            return false;
        }
        $substring = mb_substr($this->string, $this->scanLocation);
        $pattern = "(?:0[xX])?[0-9a-fA-F]+";
        if (!string_search($substring, $pattern, SearchMethod::beginsWith, CompareOptions::quoted, $matches)) {
            return false;
        }
        if (empty($matches)) {
            return false;
        }
        $value = $matches[0];
        $intValue = hexdec($value);
        $int = (int)$intValue;
        $this->scanLocation += mb_strlen((string)$value);
        return true;
    }

    /**
     * Scans for a hexadecimal float value (e.g., "0x1.fp3", "0xABC.8").
     * Supports C99/IEEE 754 hex float strings.
     *
     * @param float $float Upon return, contains the scanned value.
     * @return bool true if the receiver finds a valid hex float representation.
     */
    public function scanHexFloat(float &$float): bool
    {
        $this->skipCharacters();
        if ($this->isAtEnd) {
            return false;
        }
        $substring = mb_substr($this->string, $this->scanLocation);
        $pattern = "[-+]?0[xX](?:[0-9a-fA-F]+(?:\.[0-9a-fA-F]*)?|\.[0-9a-fA-F]+)(?:[pP][-+]?[0-9]+)?";
        if (!string_search($substring, $pattern, SearchMethod::beginsWith, CompareOptions::quoted, $matches)) {
            return false;
        }
        if (empty($matches)) {
            return false;
        }
        $value = $matches[0];
        $cleanValue = str_replace(["p", "P"], "p", $value);
        $parts = explode("p", $cleanValue);
        $mantissaPart = $parts[0];
        $exponent = isset($parts[1]) ? (int)$parts[1] : 0;
        $sign = 1;
        if (str_starts_with($mantissaPart, "-")) {
            $sign = -1;
            $mantissaPart = substr($mantissaPart, 1);
        } elseif (str_starts_with($mantissaPart, "+")) {
            $mantissaPart = substr($mantissaPart, 1);
        }
        if (str_starts_with(strtolower($mantissaPart), "0x")) {
            $mantissaPart = substr($mantissaPart, 2);
        }
        $hexParts = explode(".", $mantissaPart);
        $intPartHex = $hexParts[0] ?? "0";
        $fracPartHex = $hexParts[1] ?? "";
        $intVal = $intPartHex !== "" ? hexdec($intPartHex) : 0;
        $fracVal = 0;
        if ($fracPartHex !== "") {
            $fracVal = hexdec($fracPartHex) / pow(16, strlen($fracPartHex));
        }
        $float = $sign * ($intVal + $fracVal) * pow(2, $exponent);
        $this->scanLocation += mb_strlen((string)$value);
        return true;
    }

    private function skipCharacters(): void
    {
        $scanLocation = $this->scanLocation;
        while ($scanLocation < $this->length) {
            $char = $this->chars[$scanLocation];
            if (!in_string($this->charactersToBeSkipped, $char, $this->caseSensitive ? CompareOptions::none : CompareOptions::caseInsensitive)) {
                break;
            }
            $scanLocation++;
        }
        $this->scanLocation = $scanLocation;
    }
}
