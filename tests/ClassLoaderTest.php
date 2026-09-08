<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ClassLoader;
use Sabatier\Foundation\URL;

final class ClassLoaderTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-class-loader-" . (int)getmypid() . "-" . spl_object_id($this);
        mkdir($this->root . DIRECTORY_SEPARATOR . "src", 0777, true);
        mkdir($this->root . DIRECTORY_SEPARATOR . "vendor", 0777, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->root . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "LoadedWidget.php");
        @unlink($this->root . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php");
        @rmdir($this->root . DIRECTORY_SEPARATOR . "src");
        @rmdir($this->root . DIRECTORY_SEPARATOR . "vendor");
        @rmdir($this->root);
    }

    public function testReturnsAnAlreadyLoadedClass(): void
    {
        $loader = new ClassLoader(URL::fileURL($this->root));

        $this->assertSame(URL::class, $loader->load(URL::class));
    }

    public function testReturnsNullWhenNoMatchingClassExists(): void
    {
        $loader = new ClassLoader(URL::fileURL($this->root));

        $this->assertNull($loader->load("Sabatier\\Foundation\\Tests\\MissingClass"));
    }

    public function testLoadsAClassThroughTheBundlesAutoloader(): void
    {
        file_put_contents($this->root . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "LoadedWidget.php", <<<'PHP'
<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests\ClassLoaderFixture;

/** @internal */
final class LoadedWidget
{
}
PHP);
        file_put_contents($this->root . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php", <<<'PHP'
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/src/LoadedWidget.php";
PHP);
        $loader = new ClassLoader(URL::fileURL($this->root));

        $class = $loader->load("Sabatier\\Foundation\\Tests\\ClassLoaderFixture\\LoadedWidget");

        $this->assertSame("Sabatier\\Foundation\\Tests\\ClassLoaderFixture\\LoadedWidget", $class);
    }
}
