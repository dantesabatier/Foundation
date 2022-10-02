<?php

namespace Sabatier\Foundation;

class DataBase64EncodingOptions
{
    /** @var int Set the maximum line length to 64 characters, after which a line ending is inserted. */
    const lineLength64Characters = 1 << 0;
    /** @var int Set the maximum line length to 76 characters, after which a line ending is inserted. */
    const lineLength76Characters = 1 << 1;
    /** @var int When a maximum line length is set, specify that the line ending to insert should include a carriage return. */
    const endLineWithCarriageReturn = 1 << 4;
    /** @var int When a maximum line length is set, specify that the line ending to insert should include a line feed. */
    const endLineWithLineFeed = 1 << 5;
}
