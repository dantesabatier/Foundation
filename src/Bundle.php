<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 03/09/20
 * Time: 10:39
 */

namespace Sabatier\Foundation;

use GdImage;
use Locale;
use ReflectionClass;
use Throwable;

/**
 * A representation of the code and resources stored in a bundle directory on disk.
 */
final class Bundle extends ObjectClass
{
    /** @var Dictionary<Bundle>|null $loadedBundles */
    private static ?Dictionary $loadedBundles = null;
    public const string didLoadNotification = BundleDidLoadNotification;
    /** @var URL|null The file URL of the bundle's subdirectory containing resource files. */
    public ?URL $resourceURL {
        get => $this->directoryURL($this->bundleURL, "Resources");
    }
    /** @var URL|null The file URL of the receiver's executable file. */
    public ?URL $executableURL {
        get => $this->directoryURL($this->bundleURL->appendingPathComponent("OS"), $this->object(kCFBundleExecutableKey) ?? $this->object(kCFBundleNameKey));
    }
    /** @var URL|null The file URL of the bundle's subdirectory containing private frameworks. */
    public ?URL $privateFrameworksURL {
        get => $this->directoryURL($this->bundleURL, "PrivateFrameworks");
    }
    /** @var URL|null The file URL of the receiver's subdirectory containing shared frameworks. */
    public ?URL $sharedFrameworksURL {
        get => $this->directoryURL($this->bundleURL, "Frameworks");
    }
    /** @var URL|null The file URL of the receiver's subdirectory containing plug-ins. */
    public ?URL $builtInPlugInsURL {
        get => $this->directoryURL($this->bundleURL, "Plugins");
    }
    /** @var URL|null The file URL of the bundle's subdirectory containing shared support files. */
    public ?URL $sharedSupportURL {
        get => $this->directoryURL($this->bundleURL, "SharedSupport");
    }
    /** @var string|null The receiver's bundle identifier. */
    public ?string $bundleIdentifier {
        get => $this->object(kCFBundleIdentifierKey);
    }
    /** @var Dictionary|null A dictionary, constructed from the bundle's Info.plist file, that contains information about the receiver. */
    public ?Dictionary $infoDictionary {
        get => PropertyListSerialization::propertyListWithURL($this->bundleURL->appendingPathComponent("Info")->appendingPathExtension("plist"));
    }
    /** @var ArrayClass<string> $localizations A list of all the localizations contained in the bundle. An array of string objects containing language IDs for all the localizations contained in the bundle. */
    public ArrayClass $localizations {
        get => $this->object(kCFBundleLocalizationsKey) ?? new ArrayClass();
    }
    /** @var ArrayClass<string> $preferredLocalizations An ordered list of preferred localizations contained in the bundle. An array of string objects containing language IDs for localizations in the bundle. The strings are ordered according to the user's language preferences and available localizations */
    public ArrayClass $preferredLocalizations {
        get {
            $preferredLocalizations = clone $this->localizations;
            $preferredLocalizations->partition(fn(string $localization): bool => $localization !== Locale::getPrimaryLanguage(Locale::getDefault()));
            return $preferredLocalizations;
        }
    }
    /** @var string|null The localization for the development language.
     * This property corresponds to the value in the CFBundleDevelopmentRegion key of the bundle's property list (Info.plist). */
    public ?string $developmentLocalization {
        get => $this->object(kCFBundleDevelopmentRegionKey);
    }
    /** @var Dictionary|null A dictionary with the keys from the bundle's localized property list. This property uses the preferred localization for the current user when determining which resources to include. If the preferred localization is not available, this property chooses the most appropriate localization found in the bundle. */
    public ?Dictionary $localizedInfoDictionary {
        get => $this->infoDictionary;
    }
    /** @var class-string|null $principalClass The bundle's principal class. */
    public ?string $principalClass {
        get {
            /** @var class-string|null $principalClass */
            $principalClass = $this->object(kCFBundlePrincipalClassKey);
            return empty($principalClass) ? null : $this->classNamed($principalClass);
        }
    }

    /**
     * Returns a Bundle object initialized to correspond to the specified file URL.
     * @param URL $bundleURL The file URL to a directory. This must be a full URL for a directory; if it contains any symbolic links, they must be resolvable.
     */
    private function __construct(public readonly URL $bundleURL)
    {
        FileManager::default()->fileExists($this->bundleURL->path, $isDirectory) && $isDirectory ?: fatal_error("Invalid bundle url \"$this->bundleURL\"");
    }

    public function __destruct()
    {
        self::loadedBundles()->removeValueForKey($this->bundleURL->absoluteString);
    }

    private function directoryURL(URL $baseURL, string $name): ?URL
    {
        $url = $baseURL->appendingPathComponent($name);
        if (FileManager::default()->fileExists($url->path, $isDirectory) && $isDirectory) {
            return $url;
        }
        return null;
    }

    /**
     * @return Dictionary<Bundle>
     */
    private static function loadedBundles(): Dictionary
    {
        self::$loadedBundles ??= new Dictionary();
        return self::$loadedBundles;
    }

    /**
     * Returns a Bundle object initialized to correspond to the specified file URL.
     *
     * This method initializes and returns a new instance only if there is no existing bundle associated with url, otherwise it deallocates self and returns the existing object.
     * @param URL $url The file URL to a directory. This must be a full URL for a directory; if it contains any symbolic links, they must be resolvable.
     * @return Bundle A Bundle object initialized to correspond to url.
     */
    public static function bundleWithURL(URL $url): Bundle
    {
        $key = (string)$url;
        $loadedBundles = self::loadedBundles();
        if (!($bundle = $loadedBundles[$key])) {
            $bundle = new Bundle($url);
            $loadedBundles[$key] = $bundle;
        }
        return $bundle;
    }

    /**
     * Returns a Bundle object that corresponds to the specified directory.
     * @param string $path The path to a directory. This must be a full pathname for a directory; if it contains any symbolic links, they must be resolvable.
     * This method allocates and initializes the returned object if there is no existing Bundle associated with fullPath, in which case it returns the existing object.
     * @return Bundle The Bundle object that corresponds to path, or nil if path does not identify an accessible bundle directory.
     */
    public static function bundleWithPath(string $path): Bundle
    {
        return self::bundleWithURL(URL::fileURL($path));
    }

    /**
     * Returns the Bundle instance that has the specified bundle identifier.
     *
     * This method creates and returns a new Bundle object if there is no existing bundle associated with identifier. Otherwise, the existing instance is returned.
     * @param string $identifier The identifier for an existing Bundle instance.
     * @return Bundle|null The Bundle object with the bundle identifier, or nil if the requested bundle is not found on the system.
     */
    public static function bundleWithIdentifier(string $identifier): ?Bundle
    {
        return self::allBundles()->first(fn(Bundle $bundle): bool => (($bundleIdentifier = $bundle->bundleIdentifier) && string_is_equal($bundleIdentifier, $identifier, CompareOptions::caseInsensitive)));
    }

    /**
     * Returns the Bundle object with which the specified class is associated.
     *
     * This method is typically used by frameworks and plug-ins to locate their own bundle at runtime.
     * This method may be somewhat more efficient than trying to locate the bundle using the init() method.
     * However, if the initial lookup of an already loaded and cached bundle with the specified identifier fails, this method uses potentially time-consuming heuristics to attempt to locate the bundle.
     * As an optimization, you can use the bundleWithPath() or bundleWithURL() method instead to avoid file system traversal.
     * @param class-string $class A class.
     * @return Bundle The Bundle object that dynamically loaded $class (a loadable bundle), the Bundle object for the framework in which $class is defined, or the main bundle object if $class was not dynamically loaded or is not defined in a framework.
     * This method creates and returns a new Bundle object if there is no existing bundle associated with $class. Otherwise, the existing instance is returned.
     */
    public static function bundleForClass(string $class): Bundle
    {
        try {
            if (!($path = new ReflectionClass($class)->getFileName())) {
                fatal_error();
            }
            $url = URL::fileURL($path);
            while ($url->path !== "/") {
                $url->deleteLastPathComponent();
                if (string_is_equal($url->lastPathComponent, "src", CompareOptions::caseInsensitive)) {
                    $url->deleteLastPathComponent();
                    break;
                }
            }
            return self::bundleWithURL($url);
        } catch (Throwable $throwable) {
            throw new ($throwable::class)($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * Returns the bundle object that contains the current executable.
     *
     * The main bundle lets you access the resources in the same directory as the currently running executable. For a running app, the main bundle offers access to the app's bundle directory. For code running in a framework, the main bundle offers access to the framework's bundle directory.
     * @return Bundle The Bundle object corresponding to the bundle directory that contains the current executable. This method may return a valid bundle object even for unbundled apps. It may also return nil if the bundle object could not be created, so always check the return value.
     */
    public static function main(): Bundle
    {
        return Bundle::bundleWithURL(FileManager::default()->documentRootDirectory);
    }

    /**
     * Returns an array of all the application's bundles that represent frameworks.
     * @return ArrayClass<Bundle> An array of all the application's bundles that represent frameworks.
     * Only frameworks with one or more classes in them are included.
     */
    public static function allFrameworks(): ArrayClass
    {
        return self::loadedBundles()->filter(fn(Bundle $bundle): bool => $bundle->object(kCFBundlePackageTypeKey) === "FMWK")->values;
    }

    /**
     * Returns an array of all the application's non-framework bundles.
     *
     * The returned array includes the main bundle and all bundles that have been dynamically created
     * but doesn't contain any bundles that represent frameworks.
     * @return ArrayClass<Bundle> An array of all the application's non-framework bundles.
     */
    public static function allBundles(): ArrayClass
    {
        return self::loadedBundles()->filter(fn(Bundle $bundle): bool => $bundle->object(kCFBundlePackageTypeKey) !== "FMWK")->values;
    }

    /**
     * @param URL $baseURL
     * @param string|null $name
     * @param ArrayClass<string>|null $extensions
     * @param ArrayClass<string>|null $languages
     * @param int $limit
     * @return ArrayClass<URL>|null
     */
    private static function findBundleResources(URL $baseURL, ?string $name = null, ?ArrayClass $extensions = null, ?ArrayClass $languages = null, int $limit = NotFound): ?ArrayClass
    {
        $extensions ??= new ArrayClass();
        if ($extensions->isEmpty && $name) {
            /** @var string $extension */
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            if ($extension) {
                $extensions[] = $extension;
            }
        }
        $languages ??= new ArrayClass([""]);
        $resources = $languages->flatMap(fn(string $language): ArrayClass => FileManager::default()->contentsOfDirectory($language ? $baseURL->appendingPathComponent($language) : $baseURL, null, DirectoryEnumerationOptions::skipsHiddenFiles))->filter(function (URL $url, int $idx, bool &$stop) use ($name, $extensions, $limit): bool {
            $pathExtension = $url->pathExtension;
            /** @psalm-suppress InvalidArgument */
            $ok = $name ? (string_is_equal($url->deletingPathExtension()->lastPathComponent, pathinfo($name, PATHINFO_FILENAME)) && (empty($pathExtension) || $extensions->containsElement($pathExtension))) : (empty($pathExtension) || $extensions->containsElement($pathExtension));
            $stop = $ok && $limit > 0 && $limit >= $idx;
            return $ok;
        });
        if ($resources->isEmpty) {
            return null;
        }
        return $resources;
    }

    /**
     * Returns the file URL for the resource identified by the specified name and file extension,
     * located in the specified bundle subdirectory, and limited to global resources and those associated with the specified localization.
     * @param string|null $name The name of the resource file.
     * If you specify nil, the method returns the first resource file it finds that matches the remaining criteria.
     * @param string|null $extension The filename extension of the file to locate.
     * If you specify an empty string or nil, the extension is assumed not to exist and the file URL is the first file encountered that exactly matches name.
     * @param string|null $subpath The name of the bundle subdirectory to search.
     * @param string|null $localization The language ID for the localization.
     * This parameter should correspond to the name of one of the bundle's language-specific resource directories.
     * @return URL|null The file URL for the resource file or nil if the file could not be located.
     */
    public function url(?string $name, ?string $extension = null, ?string $subpath = null, ?string $localization = null): ?URL
    {
        $baseURL = $this->resourceURL ?? $this->bundleURL;
        /** @psalm-suppress InvalidArgument */
        return self::findBundleResources($subpath ? $baseURL->appendingPathComponent($subpath) : $baseURL, $name, $extension ? new ArrayClass([$extension]) : null, $localization ? new ArrayClass([$localization]) : null, 1)?->first;
    }

    /**
     * Returns an array containing the file URLs for all bundle resources having the specified filename extension, residing in the specified resource subdirectory, and limited to global resources and those associated with the specified localization.
     * @param string|null $extension The filename extension of the files to locate.
     * If you specify an empty string or nil, the extension is assumed not to exist and all the files in subpath are returned.
     * @param string|null $subpath The name of the bundle subdirectory to search.
     * @param string|null $localization The language ID for the localization.
     * This parameter should correspond to the name of one of the bundle's language-specific resource directories.
     * @return ArrayClass<URL>|null An array containing the file URLs for all bundle resources matching the specified criteria.
     * This method returns an empty array if no matching resource files are found.
     */
    public function urls(?string $extension = null, ?string $subpath = null, ?string $localization = null): ?ArrayClass
    {
        $baseURL = $this->resourceURL ?? $this->bundleURL;
        /** @psalm-suppress InvalidArgument */
        return self::findBundleResources($subpath ? $baseURL->appendingPathComponent($subpath) : $baseURL, null, $extension ? new ArrayClass([$extension]) : null, $localization ? new ArrayClass([$localization]) : null);
    }

    /**
     * Returns the full pathname for the resource identified by the specified name and file extension, located in the specified bundle subdirectory, and limited to global resources and those associated with the specified localization.
     * @param string|null $name The name of the resource file.
     * If you specify nil, the method returns the first resource file it finds that matches the remaining criteria.
     * @param string|null $extension The filename extension of the files to locate.
     * If you specify an empty string or nil, the extension is assumed not to exist and the file is the first file encountered that exactly matches name.
     * @param string|null $subpath The name of the bundle subdirectory to search.
     * @param string|null $localization The language ID for of the localization. This parameter should correspond to the name of one of the bundle's language-specific resource directories.
     * @return string|null The full pathname for the resource file or nil if the file could not be located.
     */
    public function path(?string $name, ?string $extension = null, ?string $subpath = null, ?string $localization = null): ?string
    {
        return $this->url($name, $extension, $subpath, $localization)?->path;
    }

    /**
     * Returns an array containing the file for all bundle resources having the specified filename extension, residing in the specified resource subdirectory, and limited to global resources and those associated with the specified localization.
     * @param string|null $extension The filename extension of the files to locate.
     * @param string|null $subpath The name of the bundle subdirectory to search.
     * @param string|null $localization The language ID for the localization. This parameter should correspond to the name of one of the bundle's language-specific resource directories.
     * @return ArrayClass<string>|null An array containing the full pathnames for all bundle resources matching the specified criteria. This method returns an empty array if no matching resource files are found.
     */
    public function paths(?string $extension = null, ?string $subpath = null, ?string $localization = null): ?ArrayClass
    {
        return $this->urls($extension, $subpath, $localization)?->map(fn(URL $url): string => $url->path);
    }

    /**
     * Returns the location of the specified image resource as a URL.
     * @param string $name The name of the image resource file. Including a filename extension is optional.
     * @return URL|null A URL for the resource file or nil if the file was not found.
     */
    public function urlForImageResource(string $name): ?URL
    {
        return self::findBundleResources($this->resourceURL ?? $this->bundleURL, $name, new ArrayClass([MimeTypeJPEG, MimeTypePNG])->flatMap(fn(string $mimeType): iterable => URLFileTypeMappings::shared()->extensions($mimeType) ?? []), null, 1)?->first;
    }

    /**
     * Returns the location of the specified image resource file.
     * @param string $name The name of the image resource file, without any pathname information. Including a filename extension is optional.
     * @return string|null The absolute pathname of the resource file or nil if the file is not found.
     */
    public function pathForImageResource(string $name): ?string
    {
        return $this->urlForImageResource($name)?->path;
    }

    /**
     * Returns a resource associated with the specified name, which can be backed by multiple files representing different resolution versions of the image.
     * @param string $name The filename of the image resource file. Including a filename extension is optional.
     * @return GdImage|null The image object associated with the specified name, or nil if no file is found.
     */
    public function image(string $name): ?GdImage
    {
        if (($url = $this->urlForImageResource($name)) && ($mimeType = URLFileTypeMappings::shared()->mimeType($url->pathExtension))) {
            return match ($mimeType) {
                MimeTypeJPEG => imagecreatefromjpeg($url->path),
                MimeTypePNG => imagecreatefrompng($url->path),
                default => null,
            };
        }
        return null;
    }

    /**
     * Returns the location of the specified sound resource file.
     * @param string $name The name of the sound resource file, without any pathname information. Including a filename extension is optional.
     * @return string|null The absolute pathname of the resource file or nil if the file was not found.
     */
    public function pathForSoundResource(string $name): ?string
    {
        return self::findBundleResources($this->resourceURL ?? $this->bundleURL, $name, new ArrayClass(["mp3"]), null, 1)?->first?->path;
    }

    /**
     * Returns a localized version of the string designated by the specified key and residing in the specified table.
     * @param string $key The key for a string in the table identified by table.
     * @param string|null $value The value to return if key is nil or if a localized string for key can't be found in the table.
     * @param string|null $table The receiver's string table to search.
     * @return string A localized version of the string designated by key in table.
     */
    public function localizedString(string $key, ?string $value = null, ?string $table = null): string
    {
        $string = localized_string($key, $table ?? "Localizable", $this->resourceURL?->path ?? "");
        if ($key === $string && $value !== null) {
            return $value;
        }
        return $string;
    }

    /**
     * Returns the value associated with the specified key in the receiver's information property list.
     * @param string $key A key in the receiver's property list.
     * @return mixed The value associated with key in the receiver's property list (Info.plist).
     * The localized value of a key is returned when one is available.
     * Use of this method is preferred over other access methods because it returns the localized value of a key when one is available.
     */
    public function object(string $key): mixed
    {
        return $this->infoDictionary?->valueForKey($key);
    }

    /**
     * Returns the Class for the specified name.
     * @param string $className The name of a class.
     * @return class-string|null The Class for className.
     * Returns nil if className is not one of the classes associated with the receiver or if there is an error loading the executable code containing the class implementation.
     * @psalm-suppress UnresolvableInclude
     */
    public function classNamed(string $className): ?string
    {
        if (class_exists($className)) {
            return $className;
        }
        $components = explode("\\", $className);
        $name = $components[count($components) - 1] ?? $className;
        if (!($enumerator = FileManager::default()->enumerator($this->bundleURL->appendingPathComponent("src"), null, DirectoryEnumerationOptions::skipsHiddenFiles))) {
            return null;
        }
        foreach ($enumerator as $url) {
            $path = $url->path;
            if (!string_is_equal($url->pathExtension, "php", CompareOptions::caseInsensitive)) {
                continue;
            }
            if (!string_is_equal(pathinfo($path, PATHINFO_FILENAME), $name, CompareOptions::caseInsensitive)) {
                continue;
            }
            require_once $path;
            if (!($class = array_find(array_reverse(get_declared_classes()), fn(string $class): bool => str_ends_with($class, $className)))) {
                continue;
            }
            if (!class_exists($class)) {
                continue;
            }
            NotificationCenter::default()->postNotificationName(self::didLoadNotification, $this, new Dictionary([LoadedClasses => new ArrayClass([$class])]));
            return $class;
        }
        return null;
    }
}
