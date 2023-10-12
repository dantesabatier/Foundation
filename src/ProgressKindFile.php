<?php

namespace Sabatier\Foundation;

/**
 * The value that indicates that the progress is tracking a file operation.
 */
class ProgressKindFile extends ProgressKind
{
    /** @var string The progress is tracking the copying of a file from source to destination. */
    final const copying = "copying";
    /** @var string The progress is tracking file decompression after a download. */
    final const decompressingAfterDownloading = "decompressingAfterDownloading";
    /** @var string The progress is tracking a file download operation. */
    final const downloading = "downloading";
    /** @var string The progress is tracking a file upload operation. */
    final const uploading = "uploading";
    /** @var string The progress is tracking the receipt of a file from another source. */
    final const receiving = "receiving";
}
