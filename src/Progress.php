<?php

namespace Sabatier\Foundation;

class Progress
{
    /** @var float The total number of tracked units of work for the current progress. */
    public float $totalUnitCount = 0.0;
    /** @var float The number of completed units of work for the current job. */
    public float $completedUnitCount = 0.0;
}
