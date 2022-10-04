<?php

namespace Sabatier\Foundation\Test;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResourceKey;

class FileManagerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testCanBuildDirectoryUrlFromValidArguments(): void
    {
        self::assertInstanceOf(
            URL::class,
            FileManager::default()->url(SearchPathDirectory::applicationsDirectory)
        );
    }

    /**
     * @throws Exception
     */
    public function testCannotBuildDirectoryUrlFromInvalidArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FileManager::default()->url(SearchPathDirectory::trashDirectory, SearchPathDomainMask::system);
    }

    public function testCanLocateDocumentRootDirectoryUrl(): URL
    {
        $url = FileManager::default()->documentRootDirectory;
        self::assertDirectoryExists($url->path);
        return $url;
    }

    /**
     * @depends testCanLocateDocumentRootDirectoryUrl
     * @throws Exception
     */
    public function testCanReadContentsOfDocumentRootDirectoryUrl(URL $url): void
    {
        $keys = new ArrayClass([URLResourceKey::isDirectoryKey, URLResourceKey::nameKey]);
        $urls = FileManager::default()->contentsOfDirectory($url, null, DirectoryEnumerationOptions::skipsHiddenFiles);
        $keys = new Set($keys);
        foreach ($urls as $url) {
            $resourceValues = $url->resourceValues($keys);
            self::assertIsString($resourceValues->name);
            self::assertIsBool($resourceValues->isDirectory);
        }
    }

    /**
     * @throws Exception
     */
    public function testCanCreateDirectory(): URL
    {
        $directoryURL = FileManager::default()->temporaryDirectory->appendingPathComponent('Test');
        $path = $directoryURL->path;
        if (!FileManager::default()->fileExists($path)) {
            FileManager::default()->createDirectory($directoryURL);
        }
        self::assertDirectoryExists($path);
        return $directoryURL;
    }

    /**
     * @depends testCanCreateDirectory
     * @param URL $directoryURL
     * @return URL
     * @throws Exception
     */
    public function testCanCreateFile(URL $directoryURL): URL
    {
        $url = $directoryURL->appendingPathComponent('Test')->appendingPathExtension('txt');
        $path = $url->path;
        if (!FileManager::default()->fileExists($path)) {
            FileManager::default()->createFile($path, 'Hello!');
        }
        self::assertFileExists($path);
        return $url;
    }

    /**
     * @depends testCanCreateDirectory
     * @param URL $url
     * @throws Exception
     */
    public function testCanReadFileAttributes(URL $url): void
    {
        $attributes = FileManager::default()->attributesOfItem($url->path);
        self::assertInstanceOf(
            Date::class,
            $attributes[FileAttributeKey::creationDate]
        );
        self::assertIsBool($attributes[FileAttributeKey::immutable]);
        self::assertIsInt($attributes[FileAttributeKey::posixPermissions]);
        self::assertIsString($attributes[FileAttributeKey::type]);
    }

    /**
     * @depends testCanCreateFile
     * @param URL $url
     * @return URL
     * @throws Exception
     */
    public function testCanCreateSymbolicLink(URL $url): URL
    {
        $fileName = $url->deletingPathExtension()->lastPathComponent;
        $destinationURL = $url->deletingLastPathComponent()->appendingPathComponent("$fileName-Symbolic Link");
        if (FileManager::default()->fileExists($destinationURL->path)) {
            FileManager::default()->removeItem($destinationURL);
        }
        self::assertTrue(FileManager::default()->createSymbolicLink($url, $destinationURL));
        return $destinationURL;
    }

    /**
     * @depends testCanCreateSymbolicLink
     * @param URL $url
     * @throws Exception
     */
    public function testCanGetDestinationOfSymbolicLink(URL $url): void
    {
        self::assertFileExists(FileManager::default()->destinationOfSymbolicLink($url->path));
    }

    /**
     * @depends testCanCreateFile
     * @param URL $url
     * @throws Exception
     */
    public function testCanCreateLink(URL $url): void
    {
        $fileName = $url->deletingPathExtension()->lastPathComponent;
        $destinationURL = $url->deletingLastPathComponent()->appendingPathComponent("$fileName-Link")->appendingPathExtension($url->pathExtension);
        if (!FileManager::default()->fileExists($destinationURL->path)) {
            FileManager::default()->linkItem($url, $destinationURL);
        }
        self::assertFileExists($destinationURL->path);
    }

    /**
     * @depends testCanCreateFile
     * @param URL $url
     * @throws Exception
     */
    public function testCanReadContentsFromValidFile(URL $url): void
    {
        self::assertIsString(FileManager::default()->contents($url->path));
    }

    /**
     * @depends testCanCreateDirectory
     * @param URL $url
     * @throws Exception
     */
    public function testCannotReadContentsFromInvalidFile(URL $url): void
    {
        self::assertIsNotString(FileManager::default()->contents($url->path));
    }

    /**
     * @depends testCanCreateFile
     * @param URL $url
     * @throws Exception
     */
    public function testCanRemoveFile(URL $url): void
    {
        FileManager::default()->removeItem($url);
        self::assertFileDoesNotExist($url->path);
    }

    /**
     * @depends testCanCreateDirectory
     * @param URL $directoryURL
     * @throws Exception
     */
    public function testCanRemoveDirectory(URL $directoryURL): void
    {
        FileManager::default()->removeItem($directoryURL);
        self::assertDirectoryDoesNotExist($directoryURL->path);
    }
}
