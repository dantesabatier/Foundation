<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Exception;

/**
 * Extracts the `localized_string()` calls from a bundle's source tree into
 * gettext catalogs. For each language it writes `Resources/<lang>/LC_MESSAGES/
 * Localizable.po` (via `xgettext`) and compiles it to `.mo` (via `msgfmt`).
 *
 * The extractor is agnostic about which bundle it operates on: it reads the
 * `src` and writes the `Resources` of whatever bundle is handed to it, so the
 * same code serves every framework and every generated project.
 */
final readonly class LocalizationExtractor
{
    /**
     * @param Bundle $bundle The bundle whose `src` is scanned and whose `Resources` receive the catalogs.
     * @param ArrayClass<string> $languages The language codes to extract (e.g. "en", "es").
     * @param ArrayClass<string> $excludedFilenames Base filenames (without extension) to skip.
     */
    public function __construct(private Bundle $bundle, private ArrayClass $languages, private ArrayClass $excludedFilenames = new ArrayClass())
    {
    }

    /**
     * Runs the extraction for every configured language.
     *
     * @throws Exception
     */
    public function extract(): void
    {
        $fileManager = FileManager::default();
        $source = $this->bundle->bundleURL->appendingPathComponent("src");
        $enumerator = $fileManager->enumerator($source) ?? fatal_error("Could not create enumerator for $source");
        /** @var ArrayClass<string> $filenames */
        $filenames = new ArrayClass();
        foreach ($enumerator as $url) {
            if (!str_ends_with($url->lastPathComponent, "php")) {
                continue;
            }
            if ($this->excludedFilenames->containsElement($fileManager->displayName($url->path))) {
                continue;
            }
            $filenames->append($url->path);
        }
        $listFile = $fileManager->temporaryDirectory->appendingPathComponent("php_files_{$this->bundle->bundleIdentifier}")->appendingPathExtension("txt")->path;
        $fileManager->createFile($listFile, $filenames->join(PHP_EOL));
        $resourceURL = $this->bundle->bundleURL->appendingPathComponent("Resources");
        foreach ($this->languages as $language) {
            $directory = $resourceURL->appendingPathComponent($language)->appendingPathComponent("LC_MESSAGES");
            if (!$fileManager->fileExists($directory->path)) {
                $fileManager->createDirectory($directory, true, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
            }
            $messages = $directory->appendingPathComponent("Localizable");
            $pot = $messages->appendingPathExtension("po")->path;
            if (!$fileManager->fileExists($pot)) {
                $fileManager->createFile($pot, "");
            }
            exec("xgettext --keyword=localized_string -d Localizable --from-code=UTF-8 --no-location --no-wrap -j --files-from=$listFile -o $pot", $potOutput, $potStatus);
            $potStatus === 0 ?: fatal_error("xgettext failed with status $potStatus");
            $mo = $messages->appendingPathExtension("mo")->path;
            exec("msgfmt $pot -o $mo", $moOutput, $moStatus);
            $moStatus === 0 ?: fatal_error("msgfmt failed with status $moStatus");
        }
    }
}
