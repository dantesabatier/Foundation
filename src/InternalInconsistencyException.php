<?php

namespace Sabatier\Foundation;

use LogicException;

/**
 * An exception that occurs when an internal assertion fails and implies an unexpected condition within the called code.
 */
class InternalInconsistencyException extends LogicException
{
}
