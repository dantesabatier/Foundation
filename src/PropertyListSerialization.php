<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * Class PropertyListSerialization
 * @package Sabatier\Foundation
 * An object that converts between a property list and one of several serialized representations.
 * The PropertyListSerialization class provides methods that convert a property list to and from several serialized formats.
 * A property list is itself an array or dictionary that contains only data, string, array, dictionary, date, and number objects.
 */
class PropertyListSerialization
{
    /**
     * Returns a Data object containing a given property list in a specified format.
     * @param mixed $plist A property list object.
     * @param PropertyListSerializationFormat $format A property list format.
     * @param int $options The opt parameter is currently unused. No options should be specified.
     * @return string A data object containing plist in the format specified by format.
     */
    public static function data(mixed $plist, PropertyListSerializationFormat $format = PropertyListSerializationFormat::xml, int $options = 0): string
    {
        return (new PropertyListSerializer())->data($plist, $format, $options);
    }

    /**
     * Writes a property list to the specified stream.
     * @param mixed $plist The property list that you want to write out.
     * @param URL $url A url.
     * @param PropertyListSerializationFormat $format One of the property list formats defined in {@see PropertyListSerializationFormat}.
     * @param int $options Currently unused. Set to 0.
     * @return int The number of bytes written to the stream. A return value of 0 indicates that an error occurred.
     */
    public static function writePropertyList(mixed $plist, URL $url, PropertyListSerializationFormat $format = PropertyListSerializationFormat::xml, int $options = 0): int
    {
        return (new PropertyListSerializer())->writePropertyList($plist, $url, $format, $options);
    }

    /**
     * Creates and returns a property list from the specified data.
     * @param string $data A data object containing a serialized property list.
     * @param int $options The options used to create the property list.
     * For possible values, see {@see PropertyListSerializationMutabilityOptions}.
     * @param PropertyListSerializationFormat|null $format Upon return, contains the format that the property list was stored in.
     * Pass nil if you do not need to know the format.
     * @return mixed A property list object corresponding to the representation in data.
     * If data is not in a supported format, returns nil.
     */
    public static function propertyList(string $data, #[ExpectedValues(flagsFromClass: PropertyListSerializationMutabilityOptions::class)] int $options = 0, PropertyListSerializationFormat &$format = null): mixed
    {
        return (new PropertyListSerializer())->propertyList($data, $options, $format);
    }

    /**
     * Creates and returns a property list by reading from the specified url.
     * @param URL $url An url;
     * @param int $options The options used to create the property list.
     * For possible values, see {@see PropertyListSerializationMutabilityOptions}.
     * @param PropertyListSerializationFormat $format Upon return, contains the format that the property list was stored in {@see PropertyListSerializationFormat}.
     * Pass nil if you do not need to know the format.
     * @return mixed A property list object corresponding to the representation in data.
     * If data is not in a supported format, returns nil.
     */
    public static function propertyListWithURL(URL $url, #[ExpectedValues(flagsFromClass: PropertyListSerializationMutabilityOptions::class)] int $options = 0, PropertyListSerializationFormat $format = PropertyListSerializationFormat::xml): mixed
    {
        return (new PropertyListSerializer())->propertyListWithURL($url, $options, $format);
    }
}
