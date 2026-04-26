<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * The value that indicates that the progress is tracking a file operation.
 */
final class ProgressKindFile extends ProgressKind
{
    /** @var string The progress is tracking the copying of a file from source to destination. */
    final const string copying = "copying";
    /** @var string The progress is tracking file decompression after a download. */
    final const string decompressingAfterDownloading = "decompressingAfterDownloading";
    /** @var string The progress is tracking a file download operation. */
    final const string downloading = "downloading";
    /** @var string The progress is tracking a file upload operation. */
    final const string uploading = "uploading";
    /** @var string The progress is tracking the receipt of a file from another source. */
    final const string receiving = "receiving";
}
