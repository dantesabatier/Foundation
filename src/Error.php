<?php

namespace Sabatier\Foundation;

use Closure;
use Override;

/**
 * Information about an error condition including a domain, a domain-specific error code, and application-specific information.
 */
class Error extends ObjectClass
{
    /** @var Dictionary<Closure(Error, string): mixed>|null */
    private static ?Dictionary $userInfoProviders = null;
    /** @var string A string containing the localized description of the error. The object in the user info dictionary for the key {@see LocalizedDescriptionKey}. If the user info dictionary doesn't contain a value for {@see LocalizedDescriptionKey}, a default string is constructed from the domain and code. */
    public string $localizedDescription {
        get => $this->userInfo?->valueForKey(LocalizedDescriptionKey) ?? "The operation couldn't be completed. " . ($this->localizedFailureReason ?? "($this->domain error $this->code.)");
    }
    /** @var ArrayClass<string>|null An array containing the localized titles of buttons appropriate for displaying in an alert panel. The object in the user info dictionary for the key {@see LocalizedRecoveryOptionsErrorKey}. If the user info dictionary doesn't contain a value for {@see LocalizedRecoveryOptionsErrorKey}, this property is null. The first string is the title of the right-most and default button, the second the one to the left of that, and so on. The recovery options should be appropriate for the localizedRecoverySuggestion property. If the user info dictionary doesn't contain a value for {@see LocalizedRecoveryOptionsErrorKey}, only an OK button is displayed. */
    public ?ArrayClass $localizedRecoveryOptions {
        get => $this->userInfo?->valueForKey(LocalizedRecoveryOptionsErrorKey);
    }
    /** @var string|null A string containing the localized recovery suggestion for the error. The object in the user info dictionary for the key {@see LocalizedRecoverySuggestionErrorKey}. If the user info dictionary doesn't contain a value for {@see LocalizedRecoverySuggestionErrorKey}, this property is null. The returned string is suitable for displaying as the secondary message in an alert panel. */
    public ?string $localizedRecoverySuggestion {
        get => $this->userInfo?->valueForKey(LocalizedRecoverySuggestionErrorKey);
    }
    /** @var string|null A string containing the localized explanation of the reason for the error. The object in the user info dictionary for the key {@see LocalizedFailureReasonErrorKey}. */
    public ?string $localizedFailureReason {
        /** @noinspection SpellCheckingInspection */
        get => $this->userInfo?->valueForKey(LocalizedFailureReasonErrorKey) ?? match ($this->domain) {
            CocoaErrorDomain => match ($this->code) {
                4, 260 => "The file doesn't exist.",
                255 => "The file couldn't be locked.",
                257, 513 => "You don't have permission.",
                258, 514 => "The file name is invalid.",
                259 => "The file isn't in the correct format.",
                261, 517 => "The specified text encoding isn't applicable.",
                262, 518 => "The specified URL type isn't supported.",
                263 => "The item is too large.",
                264 => "The text encoding of the contents couldn't be determined.",
                516 => "A file with the same name already exists.",
                640 => "There isn't enough space.",
                642 => "The volume is read only.",
                1024, 2048 => "The value is invalid.",
                3072 => "The operation was cancelled.",
                3328 => "The requested operation is not supported.",
                3840 => "The data is not in the correct format.",
                3841 => "The data is in a format that this application doesn't understand.",
                3842 => "An error occurred in the source of the data.",
                3851 => "An error occurred in the destination for the data.",
                3852 => "An error occurred in the content of the data.",
                4353 => "The file is not available on iCloud yet.",
                4354 => "There isn't enough space in your account.",
                4355 => "The iCloud servers might be unreachable or your settings might be incorrect.",
                4864, 4866 => "The data isn't in the correct format.",
                4865 => "The data is missing.",
                default => null,
            },
            URLErrorDomain => match ($this->code) {
                URLErrorUnsupportedURL, URLErrorBadURL => "The specified URL type isn't supported.",
                URLErrorCannotFindHost => "Cannot find host.",
                URLErrorNetworkConnectionLost => "Network connection lost.",
                URLErrorBadServerResponse => "Bad server response.",
                URLErrorUnknown => "Unknown error.",
                URLErrorTimedOut => "The request timed out.",
                URLErrorHTTPTooManyRedirects => "Too many HTTP redirects.",
                URLErrorFileDoesNotExist => "The file doesn't exist.",
                URLErrorNoPermissionsToReadFile => "You don't have permission.",
                default => null,
            },
            POSIXErrorDomain => function_exists("posix_strerror") ? posix_strerror($this->code) : null,
            default => null,
        };
    }
    /** @var ErrorRecoveryAttempting|null The object in the user info dictionary corresponding to the {@see RecoveryAttempterErrorKey} key. If userInfo doesn't contain a value for {@see RecoveryAttempterErrorKey}, this property is null. */
    public ?ErrorRecoveryAttempting $recoveryAttempter {
        get => $this->userInfo?->valueForKey(RecoveryAttempterErrorKey);
    }
    public string $description {
        get => sprintf("Error Domain=%s Code=%s %s UserInfo=%s", $this->domain, $this->code, $this->localizedDescription, human_readable_value($this->userInfo));
    }

    /**
     * Returns an Error object initialized for a given domain and code with a given userInfo dictionary.
     * @param string $domain The error domain—this can be one of the predefined Error domains, or an arbitrary string describing a custom domain, domain must not be null. See Error Domains for a list of predefined domains.
     * @param int $code The error code for the error.
     * @param Dictionary|null $userInfo The userInfo dictionary for the error. userInfo may be null.
     */
    public function __construct(public readonly string $domain, public readonly int $code, public readonly ?Dictionary $userInfo = null)
    {
    }

    /**
     * @return Dictionary<Closure(Error, string): mixed>
     */
    private static function userInfoProviders(): Dictionary
    {
        self::$userInfoProviders ??= new Dictionary();
        return self::$userInfoProviders;
    }

    /**
     * Specifies a block to call when the corresponding property is not present in the user info dictionary.
     * @param string $errorDomain The error domain of the provider.
     * @param Closure(Error, string): mixed $provider A block to be executed synchronously at the time a corresponding property is accessed.
     */
    public static function setUserInfoValueProvider(string $errorDomain, Closure $provider): void
    {
        self::userInfoProviders()[$errorDomain] = $provider;
    }

    /**
     * Returns any user info provider specified for a given error domain.
     * @param string $errorDomain The error domain of the user info provider.
     * @return Closure(Error, string): mixed|null The user info provider of the error domain, or null if none is specified.
     */
    public function userInfoValueProvider(string $errorDomain): ?Closure
    {
        return self::userInfoProviders()[$errorDomain];
    }

    #[Override]
    public function jsonSerialize(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = $this->dictionaryWithValues(new ArrayClass(["domain", "code", "localizedDescription", "localizedRecoveryOptions", "localizedRecoverySuggestion", "localizedFailureReason"]));
        if ($userInfo = $this->userInfo) {
            $copy = clone $userInfo;
            $copy->removeAll(fn(mixed $value, string $key): bool => match ($key) {
                LocalizedDescriptionKey, LocalizedRecoveryOptionsErrorKey, LocalizedRecoverySuggestionErrorKey, LocalizedFailureReasonErrorKey => true,
                default => false
            });
            if (!$copy->isEmpty) {
                $dictionary["userInfo"] = $copy;
            }
        }
        return $dictionary;
    }
}
