<?php

namespace Sabatier\Foundation;

/**
 * Class PropertyListSerializationFormat
 * These constants are used to specify a property list serialization format.
 * @package Sabatier\Foundation
 */
enum PropertyListSerializationFormat: int
{
    /** Specifies the ASCII property list format inherited from the OpenStep APIs. */
    case openStep = 1;
    /** Specifies the XML property list format. */
    case xml = 100;
    /** Specifies the binary property list format. */
    case binary = 200;
}