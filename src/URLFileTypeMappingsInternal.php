<?php

namespace Sabatier\Foundation;

use Exception;

/** @internal */
final readonly class URLFileTypeMappingsInternal
{
    /** @var Dictionary<ArrayClass<string>> */
    public Dictionary $MIMETypeToExtensionList;
    /** @var Dictionary<string> */
    public Dictionary $extensionToMIMEType;

    public function __construct()
    {
        /** @var Dictionary<ArrayClass<string>> $MIMETypeToExtensionList */
        $MIMETypeToExtensionList = new Dictionary();
        /** @var Dictionary<string> $extensionToMIMEType */
        $extensionToMIMEType = new Dictionary();
        try {
            if (($url = Bundle::bundleForClass(self::class)->url("mime.types")) && ($contents = FileManager::default()->contents($url->path))) {
                $scanner = new Scanner($contents);
                $scanner->charactersToBeSkipped = PHP_EOL;
                while ($scanner->scanUpCharacters(PHP_EOL, $line)) {
                    /** @psalm-suppress RedundantCast */
                    if (isset($line[0]) && $line[0] !== "#" && preg_match_all("#(\S+)#", (string)$line, $matches) && isset($matches[1]) && (count($matches[1])) > 1) {
                        /** @var ArrayClass<string> $extensions */
                        $extensions = new ArrayClass($matches[1]);
                        /** @var string $mimeType */
                        $mimeType = $extensions->popFirst();
                        foreach ($extensions as $extension) {
                            $extensionToMIMEType[$extension] = $mimeType;
                        }
                        $MIMETypeToExtensionList[$mimeType] = $extensions;
                    }
                    $scanner->scanLocation += 1;
                }
            }
        } catch (Exception) {
        }
        $this->MIMETypeToExtensionList = $MIMETypeToExtensionList;
        $this->extensionToMIMEType = $extensionToMIMEType;
    }
}
