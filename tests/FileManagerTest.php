<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

/**
 * Tests for src/FileManager.php and the directory enumeration built on URL.
 *
 * Regression guards:
 *  - URLDirectoryEnumerator receives SplFileInfo objects from its iterator and must cast
 *    them before handing them to URL::fileURL() (a TypeError under strict_types);
 *  - hasDirectoryPath / fileSystemRepresentation must work with Windows drive paths
 *    ("/C:/..." vs "C:/...");
 *  - removeItem must delete symbolic links instead of silently reporting success;
 *  - createSymbolicLink must create the link at $sourceURL pointing to $destinationURL
 *    (the arguments used to be fed to symlink() in the wrong order);
 *  - copyItem must copy directories recursively and refuse an existing destination;
 *  - isDeletableFile must check the parent directory's writability, not the file's;
 *  - trashItem must keep digits in colliding names and not add a trailing dot to
 *    extensionless files;
 *  - attributesOfItem must expose posixPermissions without filetype bits.
 */
final class FileManagerTest extends TestCase
{
    private FileManager $manager;
    private string $root;
    private URL $rootURL;
    private ?string $originalPWD = null;
    private bool $hadPWD = false;

    protected function setUp(): void
    {
        $this->manager = FileManager::default();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-filemanager-test-" . getmypid();
        mkdir($this->root . DIRECTORY_SEPARATOR . "sub", 0777, true);
        file_put_contents($this->root . DIRECTORY_SEPARATOR . "a.txt", "alpha");
        file_put_contents($this->root . DIRECTORY_SEPARATOR . "sub" . DIRECTORY_SEPARATOR . "b.txt", "beta");
        $this->rootURL = URL::fileURL($this->root);
        $this->hadPWD = array_key_exists("PWD", $_SERVER);
        $this->originalPWD = $_SERVER["PWD"] ?? null;
    }

    protected function tearDown(): void
    {
        if (!$this->hadPWD) {
            unset($_SERVER["PWD"]);
        } else {
            $_SERVER["PWD"] = $this->originalPWD;
        }
        $this->wipe($this->root);
    }

    private function wipe(string $path): void
    {
        if (is_link($path)) {
            @unlink($path) || @rmdir($path);
            return;
        }
        if (is_dir($path)) {
            @chmod($path, 0755);
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== "." && $entry !== "..") {
                    $this->wipe($path . DIRECTORY_SEPARATOR . $entry);
                }
            }
            @rmdir($path);
            return;
        }
        @unlink($path);
    }

    public function testExistenceChecks(): void
    {
        $this->assertTrue($this->manager->fileExists($this->root . DIRECTORY_SEPARATOR . "a.txt"), "fileExists on a file");
        $this->assertTrue($this->manager->fileExists($this->root, $isDirectory) && $isDirectory === true, "fileExists reports a directory through its by-ref flag");
        $this->manager->fileExists($this->root . DIRECTORY_SEPARATOR . "a.txt", $isDirectory);
        $this->assertFalse($isDirectory, "the by-ref flag is false for a regular file");
        $this->assertFalse($this->manager->fileExists($this->root . DIRECTORY_SEPARATOR . "missing.bin"), "fileExists on a missing path");
        $this->assertTrue($this->manager->isReadableFile($this->root . DIRECTORY_SEPARATOR . "a.txt"), "isReadableFile");
        $this->assertSame("alpha", $this->manager->contents($this->root . DIRECTORY_SEPARATOR . "a.txt"), "contents reads the file");
    }

    public function testDirectoryListing(): void
    {
        $listing = $this->manager->contentsOfDirectory($this->rootURL);
        $this->assertSame(2, $listing->count, "contentsOfDirectory lists direct children only");
        $names = $listing->map(fn(URL $url): string => $url->lastPathComponent)->sort();
        $this->assertSame(["a.txt", "sub"], $names->array, "contentsOfDirectory returns the child names");
        $this->assertTrue($listing->allSatisfy(fn(URL $url): bool => $url->isFileURL), "contentsOfDirectory returns file URLs");
        $this->assertTrue($listing->allSatisfy(fn(URL $url): bool => $url->deletingLastPathComponent()->isEqual($this->rootURL->standardized)), "every child resolves back to its parent");

        // The deep enumerator drives URLDirectoryEnumerator, which used to pass SplFileInfo
        // straight into URL::fileURL().
        $enumerated = [];
        foreach ($this->manager->enumerator($this->rootURL) as $url) {
            $enumerated[] = $url->lastPathComponent;
        }
        sort($enumerated);
        $this->assertSame(["a.txt", "b.txt", "sub"], $enumerated, "deep enumeration yields every descendant");
    }

    public function testCreationCopyMoveRemoval(): void
    {
        $created = $this->rootURL->appendingPathComponent("nested")->appendingPathComponent("deep");
        $this->assertTrue($this->manager->createDirectory($created, true), "createDirectory with intermediates");
        $this->assertTrue($this->manager->fileExists($created->fileSystemRepresentation, $isDirectory) && $isDirectory, "created directory exists");
        $this->assertTrue($this->manager->createDirectory($created, true), "createDirectory with intermediates succeeds when the directory already exists");

        $this->assertTrue($this->manager->createFile($this->root . DIRECTORY_SEPARATOR . "c.txt", "gamma"), "createFile");
        $this->assertSame("gamma", $this->manager->contents($this->root . DIRECTORY_SEPARATOR . "c.txt"), "createFile writes the data");

        $sourceURL = $this->rootURL->appendingPathComponent("c.txt");
        $copyURL = $this->rootURL->appendingPathComponent("c-copy.txt");
        $this->assertTrue($this->manager->copyItem($sourceURL, $copyURL), "copyItem");
        $this->assertSame("gamma", $this->manager->contents($this->root . DIRECTORY_SEPARATOR . "c-copy.txt"), "copy has the same contents");
        $this->assertTrue($this->manager->fileExists($this->root . DIRECTORY_SEPARATOR . "c.txt"), "copyItem keeps the source");

        $movedURL = $this->rootURL->appendingPathComponent("c-moved.txt");
        $this->assertTrue($this->manager->moveItem($copyURL, $movedURL), "moveItem");
        $this->assertTrue($this->manager->fileExists($this->root . DIRECTORY_SEPARATOR . "c-moved.txt"), "moved file exists");
        $this->assertFalse($this->manager->fileExists($this->root . DIRECTORY_SEPARATOR . "c-copy.txt"), "moveItem removes the source");

        $this->assertTrue($this->manager->removeItem($movedURL), "removeItem on a file");
        $this->assertFalse($this->manager->fileExists($this->root . DIRECTORY_SEPARATOR . "c-moved.txt"), "removed file no longer exists");
        $this->assertTrue($this->manager->removeItem($sourceURL), "removeItem cleans up");
    }

    /**
     * Builds the tree fixture used by the directory-copy tests.
     */
    private function makeTree(): URL
    {
        $treeURL = $this->rootURL->appendingPathComponent("tree");
        $this->manager->createDirectory($treeURL->appendingPathComponent("inner"), true);
        $this->manager->createFile($this->root . DIRECTORY_SEPARATOR . "tree" . DIRECTORY_SEPARATOR . "one.txt", "uno");
        $this->manager->createFile($this->root . DIRECTORY_SEPARATOR . "tree" . DIRECTORY_SEPARATOR . ".hidden", "oculto");
        $this->manager->createFile($this->root . DIRECTORY_SEPARATOR . "tree" . DIRECTORY_SEPARATOR . "inner" . DIRECTORY_SEPARATOR . "two.txt", "dos");
        return $treeURL;
    }

    public function testDirectoryCopy(): void
    {
        $treeURL = $this->makeTree();
        $treeCopyURL = $this->rootURL->appendingPathComponent("tree-copy");
        $this->assertTrue($this->manager->copyItem($treeURL, $treeCopyURL), "copyItem copies a directory");
        $treeCopyPath = $this->root . DIRECTORY_SEPARATOR . "tree-copy";
        $this->assertSame("uno", $this->manager->contents($treeCopyPath . DIRECTORY_SEPARATOR . "one.txt"), "the copy contains the direct children");
        $this->assertSame("dos", $this->manager->contents($treeCopyPath . DIRECTORY_SEPARATOR . "inner" . DIRECTORY_SEPARATOR . "two.txt"), "copyItem descends into subdirectories");
        $this->assertTrue($this->manager->fileExists($treeCopyPath . DIRECTORY_SEPARATOR . ".hidden"), "copyItem includes hidden files");
        $this->assertSame("uno", $this->manager->contents($this->root . DIRECTORY_SEPARATOR . "tree" . DIRECTORY_SEPARATOR . "one.txt"), "copyItem keeps the source tree");

        $this->assertTrue($this->manager->removeItem($treeCopyURL), "removeItem on a directory tree");
        $this->assertFalse($this->manager->fileExists($treeCopyPath), "the removed tree no longer exists");
    }

    public function testCopyItemThrowsWhenTheDestinationAlreadyExists(): void
    {
        $treeURL = $this->makeTree();
        $treeCopyURL = $this->rootURL->appendingPathComponent("tree-copy");
        $this->manager->copyItem($treeURL, $treeCopyURL);
        // "copyItem throws when the destination already exists"
        $this->expectException(\Throwable::class);
        $this->manager->copyItem($treeURL, $treeCopyURL);
    }

    public function testSymbolicLinks(): void
    {
        $linkPath = $this->root . DIRECTORY_SEPARATOR . "a-link.txt";
        $linkURL = $this->rootURL->appendingPathComponent("a-link.txt");
        $linkTargetURL = $this->rootURL->appendingPathComponent("a.txt");
        try {
            $this->manager->createSymbolicLink($linkURL, $linkTargetURL);
        } catch (\Throwable) {
            // Creating symlinks on Windows requires Developer Mode or elevation.
            $this->markTestSkipped("symbolic links are not supported in this environment");
        }
        $this->assertTrue(is_link($linkPath), "createSymbolicLink creates the link at the source URL");
        $this->assertSame("alpha", $this->manager->contents($linkPath), "the link points at the destination URL");
        $this->assertSame(realpath($this->root . DIRECTORY_SEPARATOR . "a.txt"), $this->manager->destinationOfSymbolicLink($linkPath), "destinationOfSymbolicLink reads the link back");
        $this->assertTrue($this->manager->removeItem($linkURL), "removeItem on a symlink");
        $this->assertFalse(is_link($linkPath), "removeItem deletes the link itself");
        $this->assertTrue($this->manager->fileExists($this->root . DIRECTORY_SEPARATOR . "a.txt"), "removeItem keeps the link target");
    }

    public function testDeletability(): void
    {
        $this->assertTrue($this->manager->isDeletableFile($this->root . DIRECTORY_SEPARATOR . "a.txt"), "isDeletableFile is true for a file in a writable directory");
        $this->assertFalse($this->manager->isDeletableFile($this->root . DIRECTORY_SEPARATOR . "missing.bin"), "isDeletableFile is false for a missing file");
        if (PHP_OS_FAMILY !== "Windows") {
            // POSIX only: Windows ignores the write bit on directories.
            $lockedPath = $this->root . DIRECTORY_SEPARATOR . "locked";
            mkdir($lockedPath);
            file_put_contents($lockedPath . DIRECTORY_SEPARATOR . "captive.txt", "x");
            chmod($lockedPath . DIRECTORY_SEPARATOR . "captive.txt", 0666);
            chmod($lockedPath, 0555);
            try {
                $this->assertFalse($this->manager->isDeletableFile($lockedPath . DIRECTORY_SEPARATOR . "captive.txt"), "isDeletableFile is false for a writable file in a read-only directory");
            } finally {
                chmod($lockedPath, 0755);
            }
        }
    }

    public function testAttributes(): void
    {
        $attributes = $this->manager->attributesOfItem($this->root . DIRECTORY_SEPARATOR . "a.txt");
        $permissions = $attributes[FileAttributeKey::posixPermissions];
        $this->assertIsInt($permissions, "posixPermissions carries permission bits only, no filetype bits");
        $this->assertSame(0, $permissions & ~0o7777, "posixPermissions carries permission bits only, no filetype bits");
        $this->assertSame(5, $attributes[FileAttributeKey::size], "the size attribute matches the contents");
    }

    public function testTrash(): void
    {
        // documentRootDirectory honors $_SERVER["PWD"] on the CLI and is lazy per instance,
        // so a fresh manager confines the Trash directory to the sandbox.
        $_SERVER["PWD"] = $this->root;
        $trashManager = new FileManager();
        $trashPath = $this->root . DIRECTORY_SEPARATOR . "Trash";

        $victimPath = $this->root . DIRECTORY_SEPARATOR . "photo2024.txt";
        $victimURL = $this->rootURL->appendingPathComponent("photo2024.txt");
        $trashManager->createFile($victimPath, "first");
        $this->assertTrue($trashManager->trashItem($victimURL, $trashedURL), "trashItem moves the item to the trash");
        $this->assertSame("photo2024.txt", $trashedURL->lastPathComponent, "trashItem keeps the original name when it is free");
        $this->assertSame("first", $trashManager->contents($trashPath . DIRECTORY_SEPARATOR . "photo2024.txt"), "the trashed item lives in the Trash directory");
        $this->assertFalse($trashManager->fileExists($victimPath), "trashItem removes the original");

        $trashManager->createFile($victimPath, "second");
        $this->assertTrue($trashManager->trashItem($victimURL, $renamedURL), "trashItem resolves a name collision");
        $this->assertSame("photo2024 1.txt", $renamedURL->lastPathComponent, "the collision name keeps the digits of the original");
        $this->assertSame("second", $trashManager->contents($renamedURL->path), "the renamed trashed item keeps its contents");

        $plainPath = $this->root . DIRECTORY_SEPARATOR . "README";
        $plainURL = $this->rootURL->appendingPathComponent("README");
        $trashManager->createFile($plainPath, "read me");
        $this->assertTrue($trashManager->trashItem($plainURL, $plainTrashedURL), "trashItem accepts an extensionless item");
        $this->assertSame("README", $plainTrashedURL->lastPathComponent, "no trailing dot is added to an extensionless name");
        $trashManager->createFile($plainPath, "read me again");
        $this->assertTrue($trashManager->trashItem($plainURL, $plainRenamedURL), "trashItem resolves an extensionless collision");
        $this->assertSame("README 1", $plainRenamedURL->lastPathComponent, "the extensionless collision name has no dot either");
    }

    public function testURLFilesystemSemantics(): void
    {
        $this->assertTrue($this->rootURL->hasDirectoryPath, "hasDirectoryPath consults the filesystem for an existing directory");
        $this->assertFalse($this->rootURL->appendingPathComponent("a.txt")->hasDirectoryPath, "hasDirectoryPath is false for an existing file");
        $expected = realpath($this->root . DIRECTORY_SEPARATOR . "a.txt");
        $this->assertNotFalse($expected, "fileSystemRepresentation matches realpath");
        $this->assertSame($expected, $this->rootURL->appendingPathComponent("a.txt")->fileSystemRepresentation, "fileSystemRepresentation matches realpath");
    }
}
