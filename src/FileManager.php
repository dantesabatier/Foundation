<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 05/07/20
 * Time: 11:41
 */

namespace Sabatier\Foundation;

use Closure;
use Exception;
use JetBrains\PhpStorm\ExpectedValues;

/**
 * A convenient interface to the contents of the file system, and the primary means of interacting with it.
 */
final class FileManager extends ObjectClass
{
    private static ?FileManager $default = null;
    /** @var FileManagerDelegate|null The delegate of the file manager object. It is recommended that you assign a delegate to the file manager object only if you allocated and initialized the object yourself. Avoid assigning a delegate to the shared file manager obtained from the default method. */
    public ?FileManagerDelegate $delegate = null;
    /** @var URL The system root directory. */
    public URL $systemRootDirectory {
        get => $this->systemRootDirectory ??= URL::fileURL("/");
    }
    /** @var URL The home directory for the current user. */
    public URL $homeDirectoryForCurrentUser {
        get => $this->homeDirectoryForCurrentUser ??= URL::fileURL(home_directory());
    }
    /** @var URL The temporary directory for the current user. */
    public URL $temporaryDirectory {
        get => $this->temporaryDirectory ??= URL::fileURL(temporary_directory());
    }
    /** @var URL The document root directory. */
    public URL $documentRootDirectory {
        get => $this->documentRootDirectory ??= URL::fileURL(document_root_directory());
    }

    /**
     * The shared file manager object for the process.
     */
    public static function default(): FileManager
    {
        self::$default ??= new FileManager();
        return self::$default;
    }

    /**
     * Returns an array of URLs for the specified common directory in the requested domains.
     * @param SearchPathDirectory $directory The search path directory.
     * The supported values are described in {@see SearchPathDirectory}.
     * @param int $domainMask The file system domain to search.
     * The value for this parameter is one or more of the constants described in {@see SearchPathDomainMask}.
     * @return ArrayClass<URL> An array of URL objects identifying the requested directories.
     * The directories are ordered according to the order of the domain mask constants, with items in the user domain first and items in the system domain last.
     */
    public function urls(SearchPathDirectory $directory, #[ExpectedValues(flagsFromClass: SearchPathDomainMask::class)] int $domainMask = SearchPathDomainMask::local): ArrayClass
    {
        /** @var ArrayClass<URL> $urls */
        $urls = new ArrayClass();
        $dirname = match ($directory) {
            SearchPathDirectory::applicationsDirectory => "Applications",
            SearchPathDirectory::libraryDirectory => "Library",
            SearchPathDirectory::documentsDirectory => "Documents",
            SearchPathDirectory::desktopDirectory => "Desktop",
            SearchPathDirectory::cachesDirectory => "Caches",
            SearchPathDirectory::applicationSupportDirectory => "Application Support",
            SearchPathDirectory::downloadsDirectory => "Downloads",
            SearchPathDirectory::moviesDirectory => "Movies",
            SearchPathDirectory::musicDirectory => "Music",
            SearchPathDirectory::picturesDirectory => "Pictures",
            SearchPathDirectory::sharedPublicDirectory => "Public",
            SearchPathDirectory::itemReplacementDirectory => "Temp",
            SearchPathDirectory::trashDirectory => "Trash"
        };
        switch ($directory) {
            case SearchPathDirectory::applicationsDirectory:
            case SearchPathDirectory::libraryDirectory:
                if ($domainMask & SearchPathDomainMask::local) {
                    $urls[] = $this->documentRootDirectory->appendingPathComponent($dirname);
                }
                if ($domainMask & SearchPathDomainMask::user) {
                    $urls[] = $this->homeDirectoryForCurrentUser->appendingPathComponent($dirname);
                }
                if ($domainMask & SearchPathDomainMask::system) {
                    $urls[] = $this->systemRootDirectory->appendingPathComponent($dirname);
                }
                break;
            case SearchPathDirectory::documentsDirectory:
            case SearchPathDirectory::desktopDirectory:
            case SearchPathDirectory::downloadsDirectory:
            case SearchPathDirectory::moviesDirectory:
            case SearchPathDirectory::musicDirectory:
            case SearchPathDirectory::picturesDirectory:
            case SearchPathDirectory::sharedPublicDirectory:
            case SearchPathDirectory::trashDirectory:
                if ($domainMask & SearchPathDomainMask::local) {
                    $urls[] = $this->documentRootDirectory->appendingPathComponent($dirname);
                }
                if ($domainMask & SearchPathDomainMask::user) {
                    $urls[] = $this->homeDirectoryForCurrentUser->appendingPathComponent($dirname);
                }
                break;
            case SearchPathDirectory::itemReplacementDirectory:
            case SearchPathDirectory::cachesDirectory:
                if ($domainMask & SearchPathDomainMask::local) {
                    $urls->appendContentsOf($this->urls(SearchPathDirectory::libraryDirectory, $domainMask)->map(fn(URL $url): URL => $url->appendingPathComponent($dirname)));
                }
                if ($domainMask & SearchPathDomainMask::user || $domainMask & SearchPathDomainMask::system) {
                    $urls[] = $this->temporaryDirectory;
                }
                break;
            case SearchPathDirectory::applicationSupportDirectory:
                $urls->appendContentsOf($this->urls(SearchPathDirectory::libraryDirectory, $domainMask)->map(fn(URL $url): URL => $url->appendingPathComponent($dirname)));
                break;
        }
        return $urls;
    }

    /**
     * Locates and optionally creates the specified common directory in a domain.
     * @param SearchPathDirectory $directory The search path directory.
     * The supported values are described in {@see SearchPathDirectory}.
     * @param int $domain The file system domain to search.
     * The value for this parameter is one of the constants described in {@see SearchPathDomainMask}.
     * You should specify only one domain for your search, and you may not specify the {@see SearchPathDomainMask::all} constant for this parameter.
     * @param URL|null $url The file URL used to determine the location of the returned URL.
     * Only the volume of this parameter is used.
     * This parameter is ignored unless the directory parameter contains the value {@see SearchPathDirectory::itemReplacement} and the domain parameter contains the value {@see SearchPathDomainMask::user}.
     * @param bool $shouldCreate Whether to create the directory if it does not already exist.
     * When creating a temporary directory, this parameter is ignored and the directory is always created.
     * @return URL The URL for the requested directory.
     * @throws Exception
     */
    public function url(SearchPathDirectory $directory, #[ExpectedValues(flagsFromClass: SearchPathDomainMask::class)] int $domain = SearchPathDomainMask::local, ?URL $url = null, bool $shouldCreate = false): URL
    {
        $fileURL = $this->urls($directory, $domain)->first ?? fatal_error();
        if ($directory === SearchPathDirectory::itemReplacementDirectory) {
            if ($url && ($domain & SearchPathDomainMask::user)) {
                $components = new URLComponents($fileURL->absoluteString);
                $components->host = $url->host;
                $componentsURL = $components->url;
                if ($componentsURL !== null) {
                    $fileURL = $componentsURL;
                }
            }
            $shouldCreate = true;
        }
        if ($shouldCreate && !$this->fileExists($fileURL->path)) {
            $this->createDirectory($fileURL, true);
        }
        return $fileURL;
    }

    /**
     * Performs a shallow search of the specified directory and returns URLs for the contained items.
     * @param URL $url The URL for the directory whose contents you want to enumerate.
     * @param ArrayClass<string>|null $keys An array of keys that identify the file properties that you want pre-fetched for each item in the directory. For each returned URL, the specified properties are fetched and cached in the URL object.
     * If you want directory contents to have no pre-fetched file properties, pass an empty array to this parameter. If you want directory contents to have default set of pre-fetched file properties, pass nil to this parameter.
     * @param int $options Options for the enumeration. Because this method performs only shallow enumerations, options that prevent descending into subdirectories or packages are not allowed; the only supported option is {@see DirectoryEnumerationOptions::skipsHiddenFiles}.
     * @return ArrayClass<URL> An array of URL objects, each of which identifies a file, directory, or symbolic link contained in url.
     * If the directory contains no entries, this method returns an empty array.
     */
    public function contentsOfDirectory(URL $url, ?ArrayClass $keys = null, #[ExpectedValues(flagsFromClass: DirectoryEnumerationOptions::class)] int $options = 0): ArrayClass
    {
        if (!($options & DirectoryEnumerationOptions::skipsSubdirectoryDescendants)) {
            $options |= DirectoryEnumerationOptions::skipsSubdirectoryDescendants;
        }
        if (!($options & DirectoryEnumerationOptions::skipsPackageDescendants)) {
            $options |= DirectoryEnumerationOptions::skipsPackageDescendants;
        }
        return new ArrayClass($this->enumerator($url, $keys, $options) ?? []);
    }

    /**
     * Performs a shallow search of the specified directory and returns the paths of any contained items.
     * @param string $path The path to the directory whose contents you want to enumerate.
     * @return ArrayClass<string> An array of string, each of which identifies a file, directory, or symbolic link contained in path. Returns an empty array if the directory exists but has no contents.
     */
    public function contentsOfDirectoryAtPath(string $path): ArrayClass
    {
        return $this->contentsOfDirectory(URL::fileURL($path), null, DirectoryEnumerationOptions::skipsHiddenFiles)->map(fn(URL $url): string => $url->path);
    }

    /**
     * Returns a directory iterator object that can be used to perform a deep iteration of the directory at the specified URL.
     * @param URL $url The location of the directory for which you want an enumeration.
     * This URL must not be a symbolic link that points to the desired directory.
     * You can use the {@see URL::resolvingSymlinksInPath} method to resolve any symlinks in the URL.
     * @param ArrayClass<string>|null $keys An array of keys that identify the properties that you want pre-fetched for each item in the enumeration. The values for these keys are cached in the corresponding URL objects. You may specify nil for this parameter.
     * @param int $options Options for the enumeration. For a list of valid options, see {@see DirectoryEnumerationOptions}.
     * @param Closure(URL, Error): bool|null $errorHandler An optional error handler block for the file manager to call when an error occurs. The handler block should return true if you want the enumeration to continue or false if you want the enumeration to stop.
     * @return DirectoryEnumerator<URL>|null A directory enumerator object that enumerates the contents of the directory at url.
     */
    public function enumerator(URL $url, ?ArrayClass $keys = null, #[ExpectedValues(flagsFromClass: DirectoryEnumerationOptions::class)] int $options = 0, ?Closure $errorHandler = null): ?DirectoryEnumerator
    {
        if ($this->fileExists($url->path, $isDirectory) && $isDirectory) {
            return new URLDirectoryEnumerator($url, $keys, $options, $errorHandler);
        }
        return null;
    }

    /**
     * Creates a directory with the given attributes at the specified URL.
     * @param URL $url A file URL that specifies the directory to create.
     * If you want to specify a relative path, you must set the current working directory before creating the corresponding URL object.
     * @param bool $createIntermediates If true, this method creates any nonexistent parent directories as part of creating the directory in url.
     * If false, this method fails if any of the intermediate parent directories does not exist.
     * @param Dictionary|null $attributes The file attributes for the new directory.
     * You can set the owner and group numbers, file permissions, and modification date.
     * @return bool true if the directory was created, true if createIntermediates is set and the directory already exists, or false if an error occurred.
     * @throws Exception This method fails if any of the intermediate parent directories does not exist.
     */
    public function createDirectory(URL $url, bool $createIntermediates = false, ?Dictionary $attributes = null): bool
    {
        return unsafe_value(function () use ($url, $createIntermediates, $attributes): bool {
            $path = $url->path;
            /** @var int $posixPermissions */
            $posixPermissions = $attributes?->valueForKey(FileAttributeKey::posixPermissions) ?? 0755;
            if (mkdir($path, $posixPermissions, $createIntermediates)) {
                $this->setNewAttributes($attributes, $path);
                return true;
            }
            return false;
        });
    }

    /**
     * Creates a file with the specified content and attributes at the given location.
     *
     * If you specify nil for the attributes' parameter, this method uses a default set of values for the owner, group, and permissions of any newly created directories in the path. Similarly, if you omit a specific attribute, the default value is used. The default values for newly created files are as follows:
     * Permissions are set according to the umask of the current process. For more information, see umask.
     * The owner ID is set to the effective user ID of the process.
     * The group ID is set to that of the parent directory.
     * If a file already exists at path, this method overwrites the contents of that file if the current process has the appropriate privileges to do so.
     * @param string $path The path for the new file.
     * @param string|null $data A data object containing the contents of the new file.
     * @param Dictionary|null $attributes A dictionary containing the attributes to associate with the new file. You can use these attributes to set the owner and group numbers, file permissions, and modification date. For a list of keys, see {@see FileAttributeKey}. If you specify nil for attributes, the file is created with a set of default attributes.
     * @return bool true if the operation was successful or if the item already exists, otherwise false.
     * @throws Exception
     */
    public function createFile(string $path, ?string $data, ?Dictionary $attributes = null): bool
    {
        return unsafe_value(function () use ($path, $data, $attributes): bool {
            $result = file_put_contents($path, $data ?? "");
            if ($result === false) {
                return false;
            }
            /** @var int|null $posixPermissions */
            $posixPermissions = $attributes?->valueForKey(FileAttributeKey::posixPermissions);
            if ($posixPermissions !== null) {
                chmod($path, $posixPermissions);
            }
            $this->setNewAttributes($attributes, $path);
            return true;
        });
    }

    /**
     * Removes the file or directory at the specified URL.
     * @param URL $fileURL A file URL specifying the file or directory to remove.
     * If the URL specifies a directory, the contents of that directory are recursively removed.
     * @return bool true if the item was removed successfully. Returns false if an error occurred.
     * If the delegate stops the operation for a file, this method returns true.
     * However, if the delegate stops the operation for a directory, this method returns false.
     * @throws Exception
     */
    public function removeItem(URL $fileURL): bool
    {
        return $this->fileExists($fileURL->path) && unsafe_value(function () use ($fileURL): bool {
                $process = function () use ($fileURL): bool {
                    $path = $fileURL->path;
                    if (is_link($path)) {
                        return true;
                    }
                    if (!$fileURL->hasDirectoryPath) {
                        return unlink($path);
                    }
                    if ($enumerator = $this->enumerator($fileURL)) {
                        foreach ($enumerator as $url) {
                            $this->removeItem($url);
                        }
                    }
                    return rmdir($path);
                };
                if ($delegate = $this->delegate) {
                    return $delegate->fileManagerShouldRemoveItemAtURL($this, $fileURL) && $process();
                }
                return $process();
            });
    }

    /**
     * Moves an item to the trash.
     * @param URL $url The item to move to the trash.
     * @param URL|null $resultingItemURL On input, a pointer to a URL object. On output, this pointer is set to the item's location in the trash. The actual name of the item may be changed when moving it to the trash, so use this URL to access it.
     * @return bool true if the item at url was successfully moved to the trash, or false if the item was not moved to the trash.
     * @throws Exception
     * @param-out URL $resultingItemURL
     */
    public function trashItem(URL $url, ?URL &$resultingItemURL = null): bool
    {
        $directory = $this->url(SearchPathDirectory::trashDirectory, SearchPathDomainMask::local, null, true);
        $filename = (function (string $name, string $extension, URL $directoryURL): string {
            $index = 1;
            while ($this->fileExists($directoryURL->appendingPathComponent($name)->appendingPathExtension($extension)->path)) {
                $name = preg_replace("/\d+/u", "", $name) . $index;
                $index++;
            }
            return "$name.$extension";
        })($url->deletingPathExtension()->lastPathComponent, $url->pathExtension, $directory);
        $resultingItemURL = $directory->appendingPathComponent($filename);
        return $this->moveItem($url, $resultingItemURL);
    }

    /**
     * Copies the file at the specified URL to a new location synchronously.
     * @param URL $sourceURL The file URL that identifies the file you want to copy.
     * The URL in this parameter must not be a file reference URL.
     * @param URL $destinationURL The URL at which to place the copy of srcURL.
     * The URL in this parameter must not be a file reference URL and must include the name of the file in its new location.
     * @return bool true if the item was copied successfully or the file manager's delegate stopped the operation deliberately.
     * Returns false if an error occurred.
     * When copying items, the current process must have permission to read the file or directory at sourceURL and write the parent directory of destinationURL.
     * If the item at srcURL is a directory, this method copies the directory and all of its contents, including any hidden files.
     * If a file with the same name already exists at dstURL, this method stops the copy attempt and returns an appropriate error.
     * If the last component of srcURL is a symbolic link, only the link is copied to the new path.
     * Prior to copying each item, the file manager asks its delegate if it should actually do so.
     * It does this by calling the {@see FileManagerDelegate::fileManagerShouldCopyItemAtURL()} method;
     * If the delegate method returns true, or if the delegate does not implement the appropriate methods,
     * the file manager proceeds to copy the file or directory
     * @throws Exception
     */
    public function copyItem(URL $sourceURL, URL $destinationURL): bool
    {
        return unsafe_value(function () use ($sourceURL, $destinationURL): bool {
            $process = fn(): bool => copy($sourceURL->path, $destinationURL->path);
            if ($delegate = $this->delegate) {
                return $delegate->fileManagerShouldCopyItemAtURL($this, $sourceURL, $destinationURL) && $process();
            }
            return $process();
        });
    }

    /**
     * Moves the file or directory at the specified URL to a new location synchronously.
     * @param URL $sourceURL The file URL that identifies the file or directory you want to move.
     * The URL in this parameter must not be a file reference URL.
     * @param URL $destinationURL The new location for the item in sourceURL.
     * The URL in this parameter must not be a file reference URL and must include the name of the file or directory in its new location.
     * @return bool true if the item was moved successfully or the file manager's delegate stopped the operation deliberately. Returns false if an error occurred.
     * When moving items, the current process must have permission to read the item at sourceURL and write the parent directory of destinationURL.
     * If the item at srcURL is a directory, this method moves the directory and all of its contents, including any hidden files.
     * If an item with the same name already exists at dstURL, this method stops the move attempt and returns an appropriate error.
     * Prior to moving the item, the file manager asks its delegate if it should actually move it.
     * It does this by calling the {@see FileManagerDelegate::fileManagerShouldMoveItemAtURL()} method.
     * If the item being moved is a directory, the file manager notifies the delegate only for the directory itself and not for any of its contents.
     * If the delegate method returns true, or if the delegate does not implement the appropriate methods, the file manager moves the file.
     * @throws Exception
     */
    public function moveItem(URL $sourceURL, URL $destinationURL): bool
    {
        return unsafe_value(function () use ($sourceURL, $destinationURL): bool {
            $process = fn(): bool => rename($sourceURL->path, $destinationURL->path);
            if ($delegate = $this->delegate) {
                return $delegate->fileManagerShouldMoveItemAtURL($this, $sourceURL, $destinationURL) && $process();
            }
            return $process();
        });
    }

    /**
     * Creates a symbolic link at the specified URL that points to an item at the given URL.
     * @param URL $sourceURL The file URL at which to create the new symbolic link. The last path component of the URL issued as the name of the link.
     * @param URL $destinationURL The file URL that contains the item to be pointed to by the link.
     * In other words, this is the destination of the link.
     * @return bool true if the symbolic link was created or false if an error occurred.
     * This method also returns false if a file, directory, or link already exists at url.
     * @throws Exception
     */
    public function createSymbolicLink(URL $sourceURL, URL $destinationURL): bool
    {
        return unsafe_value(fn(): bool => symlink($sourceURL->fileSystemRepresentation, $destinationURL->path));
    }

    /**
     * Creates a hard link between the items at the specified URLs.
     * @param URL $sourceURL The file URL that identifies the source of the link.
     * The URL in this parameter must not be a file reference URL; it must specify the actual path to the item.
     * @param URL $destinationURL The file URL that specifies where you want to create the hard link.
     * The URL in this parameter must not be a file reference URL; it must specify the actual path to the item.
     * @return bool true if the hard link was created or false if an error occurred.
     * This method also returns false if a file, directory, or link already exists at destinationURL.
     * @throws Exception
     */
    public function linkItem(URL $sourceURL, URL $destinationURL): bool
    {
        return unsafe_value(function () use ($sourceURL, $destinationURL): bool {
            $process = fn(): bool => link($sourceURL->path, $destinationURL->path);
            if ($delegate = $this->delegate) {
                return $delegate->fileManagerShouldLinkItemAtURL($this, $sourceURL, $destinationURL) && $process();
            }
            return $process();
        });
    }

    /**
     * Returns the path of the item pointed to by a symbolic link.
     * @param string $path The path of a file or directory.
     * @return string The path of the directory or file to which the symbolic link path refers. If the symbolic link is specified as a relative path, that relative path is returned.
     * @throws Exception
     */
    public function destinationOfSymbolicLink(string $path): string
    {
        return unsafe_value(fn(): string => readlink($path));
    }

    /**
     * Returns a Boolean value that indicates whether a file or directory exists at a specified path.
     * @param string $path The path of a file or directory.
     * @param bool $isDirectory Upon return, contains true if path is a directory or if the final path element is a symbolic link that points to a directory; otherwise, contains false.
     * @return bool true if a file at the specified path exists, or false if the file's does not exist or its existence could not be determined.
     * @param-out bool $isDirectory
     */
    public function fileExists(string $path, ?bool &$isDirectory = null): bool
    {
        $isDirectory = func_num_args() > 1 && is_dir($path);
        return file_exists($path);
    }

    /**
     * Returns a Boolean value that indicates whether the invoking object appears able to read a specified file.
     * @param string $path A file path.
     * @return bool true if the current process has read privileges for the file at path; otherwise false if the process does not have read privileges or the existence of the file could not be determined.
     */
    public function isReadableFile(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * Returns a Boolean value that indicates whether the invoking object appears able to write to a specified file.
     * @param string $path A file path.
     * @return bool true if the current process has write privileges for the file at path; otherwise false if the process does not have write privileges or the existence of the file could not be determined.
     */
    public function isWritableFile(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * Returns a Boolean value that indicates whether the operating system appears able to execute a specified file.
     * @param string $path A file path.
     * @return bool true if the current process has execute privileges for the file at path; otherwise false if the process does not have execute privileges or the existence of the file could not be determined.
     */
    public function isExecutableFile(string $path): bool
    {
        return is_executable($path);
    }

    /**
     * Returns a Boolean value that indicates whether the invoking object appears able to delete a specified file.
     * @param string $path A file path.
     * @return bool true if the current process has delete privileges for the file at path; otherwise false if the process does not have delete privileges or the existence of the file could not be determined.
     */
    public function isDeletableFile(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * Returns the display name of the file or directory at a specified path.
     * @param string $path The path of a file or directory.
     * @return string The name of the file or directory at path.
     */
    public function displayName(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Returns the attributes of the item at a given path.
     * @param string $path The path of a file or directory.
     * @return Dictionary A dictionary object that describes the attributes (file, directory, symlink, and so on) of the file specified by path.
     * @throws Exception
     */
    public function attributesOfItem(string $path): Dictionary
    {
        return unsafe_value(fn(): Dictionary => new Dictionary([
            FileAttributeKey::appendOnly => is_readable($path) && !is_writable($path),
            FileAttributeKey::creationDate => new Date((float)filectime($path)),
            FileAttributeKey::modificationDate => new Date((float)filemtime($path)),
            FileAttributeKey::immutable => !is_writable($path),
            FileAttributeKey::size => filesize($path),
            FileAttributeKey::type => filetype($path),
            FileAttributeKey::posixPermissions => fileperms($path),
            FileAttributeKey::groupOwnerAccountID => filegroup($path),
            FileAttributeKey::ownerAccountID => fileowner($path)
        ]));
    }

    /**
     * Sets the attributes of the specified file or directory.
     * @param Dictionary $attributes A dictionary containing as keys the attributes to set for path and as values the corresponding value for the attribute.
     * @param string $path The path of a file or directory.
     * @return bool true if all changes succeed. If any change fails, returns false, but it is undefined whether any changes actually occurred.
     * @throws Exception
     */
    public function setAttributes(Dictionary $attributes, string $path): bool
    {
        return unsafe_value(function () use ($attributes, $path): bool {
            /** @var int|null $permissions */
            $permissions = $attributes[FileAttributeKey::posixPermissions];
            if ($permissions !== null) {
                chmod($path, $permissions);
            }
            /** @var string|null $accountName */
            $accountName = $attributes[FileAttributeKey::ownerAccountName];
            /** @var int|null $accountID */
            $accountID = $attributes[FileAttributeKey::ownerAccountID];
            if ($accountName !== null || $accountID !== null) {
                chown($path, $accountName ?? $accountID);
            }
            /** @var string|null $groupAccountName */
            $groupAccountName = $attributes[FileAttributeKey::groupOwnerAccountName];
            /** @var int|null $groupAccountID */
            $groupAccountID = $attributes[FileAttributeKey::groupOwnerAccountID];
            if ($groupAccountName !== null || $groupAccountID !== null) {
                chgrp($path, $groupAccountName ?? $groupAccountID);
            }
            return true;
        });
    }

    /**
     * Returns the contents of the file at the specified path.
     * @param string $path The path of the file whose contents you want.
     * @return string|null A Data object with the contents of the file.
     * If path specifies a directory, or if some other error occurs, this method returns nil.
     * @throws Exception
     */
    public function contents(string $path): ?string
    {
        return unsafe_value(fn(): ?string => is_dir($path) ? null : file_get_contents($path));
    }

    private function setNewAttributes(?Dictionary $attributes, string $path): void
    {
        /** @var string|null $accountName */
        $accountName = $attributes?->valueForKey(FileAttributeKey::ownerAccountName);
        /** @var int|null $accountID */
        $accountID = $attributes?->valueForKey(FileAttributeKey::ownerAccountID);
        if ($accountName !== null || $accountID !== null) {
            chown($path, $accountName ?? $accountID);
        }
        /** @var string|null $groupAccountName */
        $groupAccountName = $attributes?->valueForKey(FileAttributeKey::groupOwnerAccountName);
        /** @var int|null $groupAccountID */
        $groupAccountID = $attributes?->valueForKey(FileAttributeKey::groupOwnerAccountID);
        if ($groupAccountName !== null || $groupAccountID !== null) {
            chgrp($path, $groupAccountName ?? $groupAccountID);
        }
    }
}
