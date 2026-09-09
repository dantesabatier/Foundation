<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Exception;
use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\LocalizationExtractor;
use Sabatier\Foundation\URL;

final class LocalizationExtractorTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "Foundation Localization Extractor " . (int)getmypid();
        mkdir($this->root . DIRECTORY_SEPARATOR . "src", 0777, true);
        file_put_contents($this->root . DIRECTORY_SEPARATOR . "Info.plist", <<<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<plist>
<dict>
    <key>CFBundleIdentifier</key>
    <string>com.example.localization-tests</string>
</dict>
</plist>
PLIST);
        file_put_contents($this->root . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Included.php", '<?php localized_string("Included message");');
        file_put_contents($this->root . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Excluded.php", '<?php localized_string("Excluded message");');
        file_put_contents($this->root . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Almostphp", '<?php localized_string("Wrong extension");');
    }

    /** @throws Exception */
    #[Override]
    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $this->assertTrue(FileManager::default()->removeItem(URL::fileURL($this->root)));
        }
    }

    /** @throws Exception */
    public function testExtractWritesCatalogsForIncludedPHPFiles(): void
    {
        exec("xgettext --version", $xgettextOutput, $xgettextStatus);
        exec("msgfmt --version", $msgfmtOutput, $msgfmtStatus);
        if ($xgettextStatus !== 0 || $msgfmtStatus !== 0) {
            $this->markTestSkipped("gettext command-line tools are unavailable");
        }

        $bundle = Bundle::bundleWithPath($this->root);
        $extractor = new LocalizationExtractor($bundle, new ArrayClass(["en"]), new ArrayClass(["Excluded"]));

        $extractor->extract();

        $messages = $this->root . DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "en" . DIRECTORY_SEPARATOR . "LC_MESSAGES" . DIRECTORY_SEPARATOR . "Localizable";
        $catalog = file_get_contents($messages . ".po");
        $this->assertIsString($catalog);
        $this->assertStringContainsString("Included message", $catalog);
        $this->assertStringNotContainsString("Excluded message", $catalog);
        $this->assertStringNotContainsString("Wrong extension", $catalog);
        $this->assertFileExists($messages . ".mo");
        $this->assertGreaterThan(0, filesize($messages . ".mo"));
    }
}
