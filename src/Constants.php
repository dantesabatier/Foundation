<?php

namespace Sabatier\Foundation;

/** @var string */
const MimeTypeJPEG = 'image/jpeg';
/** @var string */
const MimeTypePNG = 'image/png';
/** @var string */
const MimeTypeAVIF = 'image/avif';
/** @var string */
const TransliteratorDefault = 'Any-Latin; Latin-ASCII;';
/** @var int A value indicating that a requested item couldn't be found or doesn't exist. NotFound is typically used by various methods and functions that search for items in serial data and return indices, such as characters in a string object or id objects in an array. */
const NotFound = -1;
/** The response length cannot be determined in advance of receiving the data from the server. For example, URLResponseUnknownLength is returned when the server HTTP response does not include a Content-Length header. */
const URLResponseUnknownLength = -1;
/** @var int The total size of the transfer cannot be determined. */
const URLSessionTransferSizeUnknown = -1.0;
/** @var string Use client certificate authentication for this protection space. */
const URLAuthenticationMethodClientCertificate = 'URLAuthenticationMethodClientCertificate';
/** @var string Negotiate whether to use Kerberos or NTLM authentication for this protection space. */
const URLAuthenticationMethodNegotiate = 'URLAuthenticationMethodNegotiate';
/** @var string Use NTLM authentication for this protection space. */
const URLAuthenticationMethodNTLM = 'URLAuthenticationMethodNTLM';
/** @var string Perform server trust authentication (certificate validation) for this protection space. */
const URLAuthenticationMethodServerTrust = 'URLAuthenticationMethodServerTrust';
/** @var string Use the default authentication method for a protocol. */
const URLAuthenticationMethodDefault = 'URLAuthenticationMethodDefault';
/** @var string Use HTML form authentication for this protection space. */
const URLAuthenticationMethodHTMLForm = 'URLAuthenticationMethodHTMLForm';
/** @var string Use HTTP basic authentication for this protection space. */
const URLAuthenticationMethodHTTPBasic = 'Basic';
/** @var string Use HTTP digest authentication for this protection space. */
const URLAuthenticationMethodHTTPDigest = 'Digest';
/** @var string The protocol type for HTTP. */
const URLProtectionSpaceHTTP = 'HTTP';
/** @var string The protocol type for HTTPS. */
const URLProtectionSpaceHTTPS = 'HTTPS';
/** @var string The protocol type for FTP. */
const URLProtectionSpaceFTP = 'FTP';
/** @var string This value transformer negates a boolean value, transforming true to false and false to true. This transformer is reversible. */
const NegateBooleanTransformerName = "NegateBoolean";
/** @var string This value transformer returns an object created by attempting to unarchive the data passed as the value. */
const UnarchiveFromDataTransformerName = "UnarchiveFromData";
/** @var string The name of the value transformer that creates then returns an object by attempting to unarchive the data to a class that supports secure coding. */
const SecureUnarchiveFromDataTransformerName = 'SecureUnarchiveFromData';
/** @var float The time interval between 1 January 1970 and the reference date 1 January 2001 00:00:00 GMT. */
const kCFAbsoluteTimeIntervalSince1970 = 978_307_200.0;
/** @var string The version of the information property list format. */
const kCFBundleInfoDictionaryVersionKey = 'CFBundleInfoDictionaryVersion';
/** @var string The name of the executable in this bundle (if any). */
const kCFBundleExecutableKey = 'CFBundleExecutable';
/** @var string The bundle identifier. */
const kCFBundleIdentifierKey = 'CFBundleIdentifier';
/** @var string The version number of the bundle. */
const kCFBundleVersionKey = 'CFBundleVersion';
/** @var string The name of the development language of the bundle. */
const kCFBundleDevelopmentRegionKey = 'CFBundleDevelopmentRegion';
/** @var string Allows an unbundled application that handles localization itself to specify which localizations it has available. */
const kCFBundleLocalizationsKey = 'CFBundleLocalizations';
/** @var string The human-readable name of the bundle. */
const kCFBundleNameKey = 'CFBundleName';
const kCFBundlePackageTypeKey = 'CFBundlePackageType';
const kCFBundleDisplayNameKey = 'CFBundleDisplayName';
const kCFBundleShortVersionStringKey = 'CFBundleShortVersionString';
const kCFBundleExecutablePathKey = 'CFBundleExecutablePath';
const kCFBundlePrincipalClassKey = 'NSPrincipalClass';
/** @var string The domain consisting of defaults parsed from the application's arguments. These are one or more pairs of the form -default value included in the command-line invocation of the application. */
const ArgumentDomain = 'ArgumentDomain';
/** @var string The domain consisting of defaults meant to be seen by all applications. */
const GlobalDomain = 'GlobalDomain';
const RegistrationDomain = 'RegistrationDomain';
/** @var string Posted when user defaults are changed within the current process. This notification is posted on the thread that changes the user defaults. The notification object is the UserDefaults object. The notification doesn't contain a userInfo dictionary.
 * This notification isn't posted when changes are made outside the current process, or when ubiquitous defaults change. You can use key-value observing to register observers for specific keys of interest in order to be notified of all updates, regardless of whether changes are made within or outside the current process.
 */
const UserDefaultsDidChangeNotification = "UserDefaultsDidChangeNotification";
/** @var string Posted when more data is stored in user defaults than is allowed. Currently, there is only a size limit for data stored to local user defaults on tvOS, which posts a warning notification when user defaults storage reaches 512kB in size, and terminates apps when user defaults storage reaches 1MB in size. */
const UserDefaultsSizeLimitExceededNotification = "UserDefaultsSizeLimitExceededNotification";
/** @var string A notification that lets observers know when classes are dynamically loaded. When a request is made to a bundle for a class ({@see Bundle::classNamed()}: or {@see Bundle::$principalClass}), the bundle dynamically loads the executable code file that contains the class implementation and all other class definitions contained in the file. After the module is loaded, the bundle posts the BundleDidLoadNotification. The notification object is the Bundle instance that dynamically loads classes. The userInfo dictionary contains a {@see LoadedClasses} key. In a typical use of this notification, an object might want to enumerate the userInfo array to check if each loaded class conformed to a certain protocol (say, a protocol for a plug-and-play tool set); if a class does conform, the object would create an instance of that class and add the instance to another Array object. */
const BundleDidLoadNotification = 'BundleDidLoadNotification';
/** @var string A constant used as a key for the userInfo dictionary of a {@see BundleDidLoadNotification} notification that corresponds to an array of names of each class that was loaded. */
const LoadedClasses = 'LoadedClasses';
const EscapeSequenceBackgroundColorAddition = 10;
