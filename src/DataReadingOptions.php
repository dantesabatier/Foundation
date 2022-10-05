<?php

namespace Sabatier\Foundation;

class DataReadingOptions
{
    /** @var int A hint indicating the file should be mapped into virtual memory, if possible and safe. */
    final const mappedIfSafe = 1 << 0;
    /** @var int A hint indicating the file should not be stored in the file-system caches. For data being read once and discarded, this option can improve performance. */
    final const uncached = 1 << 1;
    /** @var int Hint to map the file in if possible. This takes precedence over {@see mappedIfSafe} if both are given. */
    final const alwaysMapped = 1 << 3;
}
