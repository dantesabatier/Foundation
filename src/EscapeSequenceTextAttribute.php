<?php

namespace Sabatier\Foundation;

enum EscapeSequenceTextAttribute: int
{
    case normal = 0;
    case bold = 1;
    case faint = 2;
    case italic = 3;
    case underlined = 4;
    case slowBlink = 5;
    case rapidBlink = 6;
    case reverseVideo = 7;
    case conceal = 8;
    case crossedOut = 9;
    case primary = 10;
}
