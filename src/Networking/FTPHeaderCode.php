<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/** @internal */
enum FTPHeaderCode: int
{
    case transferCompleted = 226;
    case openDataConnection = 150;
    case fileStatus = 213;
    case syntaxError = 500; // 500 series FTP Syntax errors
    case errorOccurred = 400; // 400 Series FTP transfer errors
}
