<?php

namespace Sabatier\Foundation;

/** @internal */
final class URLFileTypeMappingsInternal
{
    /** @var Dictionary<ArrayClass<string>> */
    public readonly Dictionary $MIMETypeToExtensionList;
    /** @var Dictionary<string> */
    public readonly Dictionary $extensionToMIMEType;

    public function __construct()
    {
        /** @var Dictionary<ArrayClass<string>> $MIMETypeToExtensionList */
        $MIMETypeToExtensionList = new Dictionary();
        /** @var Dictionary<string> $extensionToMIMEType */
        $extensionToMIMEType = new Dictionary();
        /** @noinspection PhpUnhandledExceptionInspection */
        if (($url = Bundle::bundleForClass(self::class)->url('mime.types')) && ($contents = FileManager::default()->contents($url->path))) {
            $scanner = new Scanner($contents);
            $scanner->charactersToBeSkipped = PHP_EOL;
            while ($scanner->scanUpCharacters(PHP_EOL, $line)) {
                if (isset($line[0]) && $line[0] !== '#' && preg_match_all('#(\S+)#', $line, $matches) && isset($matches[1]) && (count($matches[1])) > 1) {
                    $array = new ArrayClass($matches[1]);
                    $mimeType = $array[0];
                    $extensions = new ArrayClass($array->dropFirst(1));
                    foreach ($extensions as $extension) {
                        $extensionToMIMEType[$extension] = $mimeType;
                    }
                    $MIMETypeToExtensionList[$mimeType] = $extensions;
                }
                $scanner->scanLocation += 1;
            }
        }
        $this->MIMETypeToExtensionList = $MIMETypeToExtensionList;
        $this->extensionToMIMEType = $extensionToMIMEType;
    }
}
