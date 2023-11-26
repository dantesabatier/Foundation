<?php

namespace Sabatier\Foundation;

/** @internal */
final class URLFileTypeMappings
{
    private static ?URLFileTypeMappings $shared = null;
    private readonly URLFileTypeMappingsInternal $internal;

    public function __construct()
    {
        $this->internal = new URLFileTypeMappingsInternal();
    }

    public static function shared(): URLFileTypeMappings
    {
        if (self::$shared === null) {
            self::$shared = new URLFileTypeMappings();
        }
        return self::$shared;
    }

    /**
     * @param string $mimeType
     * @return ArrayClass<string>|null
     */
    public function extensions(string $mimeType): ?ArrayClass
    {
        return $this->internal->MIMETypeToExtensionList[$mimeType];
    }

    public function preferredExtension(string $mimeType): ?string
    {
        return $this->extensions($mimeType)?->first;
    }

    public function mimeType(string $extension): ?string
    {
        return $this->internal->extensionToMIMEType[$extension];
    }
}
