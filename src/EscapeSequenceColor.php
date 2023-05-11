<?php

namespace Sabatier\Foundation;

/**
 * These constants are used by the {@see escape_sequence()} function.
 */
enum EscapeSequenceColor: int
{
    case black = 30;
    case red = 31;
    case green = 32;
    case yellow = 33;
    case blue = 34;
    case magenta = 35;
    case cyan = 36;
    case white = 37;
    case brightBlack = 90;
    case brightRed = 91;
    case brightGreen = 92;
    case brightYellow = 93;
    case brightBlue = 94;
    case brightMagenta = 95;
    case brightCyan = 96;
    case brightWhite = 97;
}
