<?php

namespace Sabatier\Foundation;

class DataWritingOptions
{
    /** @var int An option to write data to an auxiliary file first and then replace the original file with the auxiliary file when the write completes. */
    const atomic = 1 << 0;
    /** @var int  An option that attempts to write data to a file and fails with an error if the destination file already exists. You can't combine this constant with atomic because atomic allows the system to overwrite the original file. */
    const withoutOverwriting = 1 << 1;
    /** @var int  An option to not encrypt the file when writing it out. In this case, the system doesn't store the file in an encrypted format and your app can access this file at boot time and while the device is unlocked. */
    const noFileProtection = 0x10000000;
    /** @var int An option to make the file accessible only while the device is unlocked. In this case, the system stores the file in an encrypted format and your app may only read or write to the file while the device is unlocked. At all other times, any attempts your app makes to read and write the file will fail. */
    const completeFileProtection = 0x20000000;
    /** @var int An option to allow the file to be accessible while the device is unlocked or the file is already open. In this case, your app cannot open the file to read it or write to it when the device is locked, but your app can create new files with this class. If one of these files is open when the device is locked, your app can read and write to the opened file. */
    const completeFileProtectionUnlessOpen = 0x30000000;
}