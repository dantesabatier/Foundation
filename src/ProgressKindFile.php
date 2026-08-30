<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * The value that indicates that the progress is tracking a file operation.
 */
final class ProgressKindFile extends ProgressKind
{
    /** @var string The progress is tracking the copying of a file from source to destination. */
    const string copying = "copying";
    /** @var string The progress is tracking file decompression after a download. */
    const string decompressingAfterDownloading = "decompressingAfterDownloading";
    /** @var string The progress is tracking a file download operation. */
    const string downloading = "downloading";
    /** @var string The progress is tracking a file upload operation. */
    const string uploading = "uploading";
    /** @var string The progress is tracking the receipt of a file from another source. */
    const string receiving = "receiving";
}
