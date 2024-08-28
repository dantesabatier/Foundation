<?php

namespace Sabatier\Foundation;

/**
 * An interface to the user's defaults database, where you store key-value pairs persistently across launches of your app.
 */
class UserDefaults
{
    private static ?UserDefaults $standard = null;
    /** @var Dictionary<ApplicationPreferences>|null */
    private static ?Dictionary $standardUserPreferences = null;
    final public const string argumentDomain = ArgumentDomain;
    final public const string globalDomain = GlobalDomain;
    final public const string registrationDomain = RegistrationDomain;
    final public const string didChangeNotification = UserDefaultsDidChangeNotification;
    final public const string sizeLimitExceededNotification = UserDefaultsSizeLimitExceededNotification;
    private readonly string $suiteName;

    /**
     * Creates a user defaults object initialized with the defaults for the specified database name.
     *
     * If you pass nil to this parameter, the system uses the default search list that the {@see standard()} class method uses. Because a suite manages the defaults of a specified app group, a suite name must be distinct from your app's main bundle identifier. The {@see globalDomain} is also an invalid suite name, because it isn't writeable by apps.
     * @param string|null $suiteName The domain identifier of the search list.
     */
    public function __construct(?string $suiteName = null)
    {
        $suiteName ??= Bundle::main()->bundleIdentifier ?? fatal_error("Unable to infer a valid suite");
        $this->suiteName = $suiteName;
        $this->addSuite($this->suiteName);
    }

    /**
     * @return Dictionary<ApplicationPreferences>
     */
    private function standardUserPreferences(): Dictionary
    {
        if (self::$standardUserPreferences === null) {
            self::$standardUserPreferences = new Dictionary();
        }
        return self::$standardUserPreferences;
    }

    /**
     * Returns the shared defaults object.
     *
     * If the shared defaults object doesn't yet exist, it's created with a search list containing the names of the following domains, in this order:
     * For managed devices only, a domain containing defaults set by an administrator
     * {@see argumentDomain}, consisting of defaults parsed from the application's arguments
     * For managed devices by an educational institution only, a domain containing defaults set in the iCloud key-value store
     * A domain identified by the application's bundle identifier
     * {@see globalDomain}, consisting of defaults meant to be seen by all applications
     * {@see registrationDomain}, a set of temporary defaults whose values can be set by the application to ensure that searches will always be successful
     * The defaults are initialized for the current user. Subsequent modifications to the standard search list remain in effect even when this method is invoked again—the search list is guaranteed to be standard only the first time this method is invoked.
     * @return UserDefaults The shared defaults object.
     */
    public static function standard(): UserDefaults
    {
        if (self::$standard === null) {
            self::$standard = new UserDefaults();
        }
        return self::$standard;
    }

    /**
     * Returns the object associated with the specified key.
     *
     * This method searches the domains included in the search list in the order in which they are listed and returns the object associated with the first occurrence of the specified default.
     * The returned object is immutable, even if the value you originally set was mutable.
     * @param string $key A key in the current user's defaults database.
     * @return mixed The object associated with the specified key, or nil if the key was not found.
     */
    public function object(string $key): mixed
    {
        return $this->dictionaryRepresentation()[$key];
    }

    /**
     * Returns the URL associated with the specified key.
     *
     * This method retrieves the URL associated with a key with the following behavior:
     * If the value for the key is a Data object, the data object is used as the argument to unarchiveObject(with:). If the data object can be unarchived as a URL, the URL is returned. If the URL can't be archived as a URL, nil is returned.
     * If the value for this key is a file reference URL, the file reference URL is created, but its bookmark data isn't resolved until the URL object is later used (for example, with init(contentsOf:)).
     * If the value for the key is a string which begins with a tilde (~), the string is expanded using the expandingTildeInPath method, from which a URL with the file: scheme is created.
     * @param string $key A key in the current user's defaults database.
     * @return URL|null The URL associated with the specified key. If the key doesn't exist, this method returns nil.
     */
    public function url(string $key): ?URL
    {
        if ($object = $this->object($key)) {
            return KeyedUnarchiver::unarchiveTopLevelObjectWithData($object);
        }
        return null;
    }

    /**
     * Returns the array associated with the specified key.
     * @param string $key A key in the current user's defaults database.
     * @return ArrayClass|null The array associated with the specified key, or nil if the key does not exist or its value is not an array.
     */
    public function array(string $key): ?ArrayClass
    {
        if (($object = $this->object($key)) && $object instanceof ArrayClass) {
            return $object;
        }
        return null;
    }

    /**
     * Returns the dictionary object associated with the specified key.
     * @param string $key A key in the current user's defaults database.
     * @return Dictionary|null The dictionary object associated with the specified key, or nil if the key does not exist or its value is not a dictionary.
     */
    public function dictionary(string $key): ?Dictionary
    {
        if (($object = $this->object($key)) && $object instanceof Dictionary) {
            return $object;
        }
        return null;
    }

    /**
     * Returns the string associated with the specified key.
     * @param string $key A key in the current user's defaults database.
     * @return string|null For string values, the string associated with the specified key; for number values, the string value of the number. Returns nil if the default does not exist or is not a string or number value.
     */
    public function string(string $key): ?string
    {
        $object = $this->object($key);
        if (is_bool($object) || is_int($object) || is_float($object)) {
            return (new Number($object))->stringValue;
        } elseif (is_string($object)) {
            return $object;
        }
        return null;
    }

    /**
     * Returns the Boolean value associated with the specified key.
     *
     * This method automatically coerces certain ”truthy” values—such as the strings "true", "YES", and "1", and the numbers 1 and 1.0 to the Boolean value true. The same is true for certain ”falsy” values—such as the strings "false", "NO", and "0", and the numbers 0 and 0.0—which are automatically coerced to the Boolean value false.
     * @param string $key A key in the current user's defaults database.
     * @return bool The Boolean value associated with the specified key. If the specified key doesn't exist, this method returns false.
     */
    public function bool(string $key): bool
    {
        $object = $this->object($key);
        if (is_bool($object) || is_numeric($object)) {
            return (new Number($object))->boolValue;
        }
        return false;
    }

    /**
     * Returns the integer value associated with the specified key.
     *
     * This method automatically coerces certain values into equivalent integer values (if one can be determined). The Boolean value true becomes 1 and false becomes 0. A floating point number becomes the greatest integer that's less than that number (for example, 2.67 becomes 2). A string that represents an integer becomes the equivalent integer (for example “123“ becomes 123).
     * @param string $key A key in the current user's defaults database.
     * @return int The integer value associated with the specified key. If the specified key doesn't exist, this method returns 0.
     */
    public function integer(string $key): int
    {
        $object = $this->object($key);
        if (is_bool($object) || is_numeric($object)) {
            return (new Number($object))->intValue;
        }
        return 0;
    }

    /**
     * Returns the float value associated with the specified key.
     *
     * This method automatically coerces certain values into equivalent float values (if one can be determined). The Boolean value true becomes 1.0 and false becomes 0.0. An integer becomes the equivalent float (for example, 2 becomes 2.0). A string that represents a floating point number becomes the equivalent float (for example “123.4“ becomes 123.4).
     * @param string $key A key in the current user's defaults database.
     * @return float The float value associated with the specified key. If the key doesn't exist, this method returns 0.
     */
    public function float(string $key): float
    {
        $object = $this->object($key);
        if (is_bool($object) || is_numeric($object)) {
            return (new Number($object))->floatValue;
        }
        return 0.0;
    }

    /**
     * Returns a dictionary that contains a union of all key-value pairs in the domains in the search list.
     * @return Dictionary A dictionary containing the keys. The keys are names of defaults and the value corresponding to each key is a property list object (Data, String, Number, Date, Array, or Dictionary).
     */
    public function dictionaryRepresentation(): Dictionary
    {
        return self::standardUserPreferences()->valueForKey($this->suiteName)?->dictionaryRepresentation ?? fatal_error("Suite \"$this->suiteName\" not found");
    }

    /**
     * Sets the value of the specified default key.
     *
     * The value parameter can be only property list objects: Data, String, Number, Date, Array, or Dictionary. For Array and Dictionary objects, their contents must be property list objects.
     * @param mixed $value The object to store in the defaults database.
     * @param string $key The key with which to associate the value.
     */
    public function setObject(mixed $value, string $key): void
    {
        if ($value !== $this->dictionaryRepresentation()->updateValue($value, $key)) {
            NotificationCenter::default()->postNotificationName(self::didChangeNotification, $this);
        }
    }

    /**
     * Sets the value of the specified default key to the specified float value.
     *
     * This is a convenience method for calling {@see setObject()}.
     * @param float $value The object to store in the defaults database.
     * @param string $key The key with which to associate the value.
     */
    public function setFloat(float $value, string $key): void
    {
        $this->setObject($value, $key);
    }

    /**
     * Sets the value of the specified default key to the specified integer value.
     *
     * This is a convenience method for calling {@see setObject()}.
     * @param int $value The object to store in the defaults database.
     * @param string $key The key with which to associate the value.
     */
    public function setInteger(int $value, string $key): void
    {
        $this->setObject($value, $key);
    }

    /**
     * Sets the value of the specified default key to the specified Boolean value.
     *
     * This is a convenience method for calling {@see setObject()}.
     * @param bool $value The object to store in the defaults database.
     * @param string $key The key with which to associate the value.
     */
    public function setBool(bool $value, string $key): void
    {
        $this->setObject($value, $key);
    }

    /**
     * Sets the value of the specified default key to the specified URL.
     *
     * This is a convenience method for calling {@see setObject()}.
     * @param URL|null $value The URL to store in the defaults database.
     * @param string $key The key with which to associate the value.
     */
    public function setURL(?URL $value, string $key): void
    {
        $this->setObject($value ? KeyedArchiver::archivedData($value) : null, $key);
    }

    /**
     * Removes the value of the specified default key.
     * @param string $key The key whose value you want to remove.
     */
    public function removeObject(string $key): void
    {
        $this->dictionaryRepresentation()->removeValueForKey($key);
        NotificationCenter::default()->postNotificationName(self::didChangeNotification, $this);
    }

    /**
     * Adds the contents of the specified dictionary to the registration domain.
     *
     * If there is no registration domain, one is created using the specified dictionary, and {@see registrationDomain} is added to the end of the search list.
     * The contents of the registration domain are not written to disk; you need to call this method each time your application starts. You can place a plist file in the application's Resources directory and call {@see register()} with the contents that you read in from that file.
     * @param Dictionary $defaults The dictionary of keys and values you want to register.
     */
    public function register(Dictionary $defaults): void
    {
        $this->dictionaryRepresentation()->merge($defaults, fn(mixed $old, mixed $new): mixed => $old ?? $new);
    }

    /**
     * Inserts the specified domain name into the receiver's search list.
     *
     * The suiteName domain is similar to a bundle identifier string, but isn't necessarily tied to a particular application or bundle. A suite can be used to hold preferences that are shared between multiple applications.
     * @param string $named The domain name to insert.
     */
    public function addSuite(string $named): void
    {
        self::standardUserPreferences()->setValueForKey(new ApplicationPreferences($named), $named);
    }

    /**
     * Removes the specified domain name from the receiver's search list.
     * @param string $named The domain name to remove.
     */
    public function removeSuite(string $named): void
    {
        self::standardUserPreferences()->removeValueForKey($named);
    }

    /**
     * Returns a dictionary representation of the defaults for the specified domain.
     *
     * Calling this method is equivalent to initializing a user defaults object with {@see __construct()} passing domainName and calling the {@see dictionaryRepresentation()} method on it.
     * @param string $domainName The name of the domain to be represented.
     * @return Dictionary A dictionary containing keys for each default name and their corresponding default values.
     */
    public function persistentDomain(string $domainName): Dictionary
    {
        return (new UserDefaults($domainName))->dictionaryRepresentation();
    }

    /**
     * Sets a dictionary for the specified persistent domain.
     *
     * Calling this method is equivalent to initializing a user defaults object with {@see __construct()} passing domainName, and calling the {@see setObject()} method for each key-value pair in domain.
     * When a persistent domain is changed, an {@see didChangeNotification} is posted.
     * @param Dictionary $domain A dictionary of keys and values you want to assign to the domain.
     * @param string $domainName The name of the domain whose contents you want to set.
     */
    public function setPersistentDomain(Dictionary $domain, string $domainName): void
    {
        $defaults = new UserDefaults($domainName);
        $defaults->dictionaryRepresentation()->merge($domain);
        NotificationCenter::default()->postNotificationName(self::didChangeNotification, $defaults);
    }

    /**
     * Removes the contents of the specified persistent domain from the user's defaultsCalling this method is equivalent to initializing a user defaults object with {@see __construct()} passing domainName, and calling the {@see removeObject()} method on each of its keys.
     *
     * When a persistent domain is changed, an {@see didChangeNotification} is posted.
     * @param string $domainName The name of the domain to have its contents removed.
     */
    public function removePersistentDomain(string $domainName): void
    {
        $defaults = new UserDefaults($domainName);
        $defaults->dictionaryRepresentation()->removeAll();
        NotificationCenter::default()->postNotificationName(self::didChangeNotification, $defaults);
    }

    /**
     * Waits for any pending asynchronous updates to the defaults database and returns; this method is unnecessary and shouldn't be used.
     * @return bool true if the data was saved successfully to disk, otherwise false.
     */
    public function synchronize(): bool
    {
        return true;
    }

    /**
     * This method has no effect and shouldn't be used.
     */
    public static function resetStandardUserDefaults(): void
    {
    }
}
