<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Exception;
use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Foundation\URL;

final class FileManagerSearchPathTest extends TestCase
{
    private FileManager $manager;
    private string $root;
    private ?string $originalPWD;
    private bool $hadPWD;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "foundation-search-paths-" . (int)getmypid();
        mkdir($this->root, 0777, true);
        $this->hadPWD = array_key_exists("PWD", $_SERVER);
        $this->originalPWD = $_SERVER["PWD"] ?? null;
        $_SERVER["PWD"] = $this->root;
        $this->manager = new FileManager();
    }

    /** @throws Exception */
    #[Override]
    protected function tearDown(): void
    {
        try {
            if (is_dir($this->root)) {
                $this->assertTrue($this->manager->removeItem(URL::fileURL($this->root)));
            }
        } finally {
            if (!$this->hadPWD) {
                unset($_SERVER["PWD"]);
            } else {
                $_SERVER["PWD"] = $this->originalPWD;
            }
        }
    }

    public function testLocalDirectoriesAreRelativeToTheDocumentRoot(): void
    {
        $documents = $this->manager->urls(SearchPathDirectory::documentsDirectory);
        $caches = $this->manager->urls(SearchPathDirectory::cachesDirectory);
        $support = $this->manager->urls(SearchPathDirectory::applicationSupportDirectory);
        $documentRoot = $this->manager->documentRootDirectory;

        $this->assertSame([$documentRoot->appendingPathComponent("Documents")->path], $documents->map(fn(URL $url): string => $url->path)->array);
        $this->assertSame([$documentRoot->appendingPathComponent("Library")->appendingPathComponent("Caches")->path], $caches->map(fn(URL $url): string => $url->path)->array);
        $this->assertSame([$documentRoot->appendingPathComponent("Library")->appendingPathComponent("Application Support")->path], $support->map(fn(URL $url): string => $url->path)->array);
    }

    public function testLibraryDirectoriesFollowDomainOrder(): void
    {
        $libraries = $this->manager->urls(SearchPathDirectory::libraryDirectory, SearchPathDomainMask::all);
        $expected = [
            $this->manager->documentRootDirectory->appendingPathComponent("Library")->path,
            $this->manager->homeDirectoryForCurrentUser->appendingPathComponent("Library")->path,
            $this->manager->systemRootDirectory->appendingPathComponent("Library")->path,
        ];

        $this->assertSame($expected, $libraries->map(fn(URL $url): string => $url->path)->array);
    }

    /** @throws Exception */
    public function testURLCreatesTheRequestedDirectory(): void
    {
        $url = $this->manager->url(SearchPathDirectory::applicationSupportDirectory, SearchPathDomainMask::local, null, true);

        $this->assertDirectoryExists($url->path);
    }

    /** @throws Exception */
    public function testItemReplacementDirectoryIsAlwaysCreated(): void
    {
        $url = $this->manager->url(SearchPathDirectory::itemReplacementDirectory);

        $this->assertDirectoryExists($url->path);
    }

    public function testPathDirectoryListingSkipsHiddenFiles(): void
    {
        file_put_contents($this->root . DIRECTORY_SEPARATOR . "visible.txt", "visible");
        $hiddenPath = $this->root . DIRECTORY_SEPARATOR . ".hidden.txt";
        file_put_contents($hiddenPath, "hidden");

        $contents = $this->manager->contentsOfDirectoryAtPath($this->root);

        $this->assertSame([$this->manager->documentRootDirectory->appendingPathComponent("visible.txt")->path], $contents->array);
    }

    public function testDisplayNameRemovesOnlyTheFinalExtension(): void
    {
        $this->assertSame("archive.tar", $this->manager->displayName($this->root . DIRECTORY_SEPARATOR . "archive.tar.gz"));
    }
}
