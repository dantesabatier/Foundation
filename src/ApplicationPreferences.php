<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/** @internal */
final readonly class ApplicationPreferences
{
    public URL $url;
    public Dictionary $dictionaryRepresentation;
    private ObjectProtocol $observer;

    /** @noinspection PhpUnhandledExceptionInspection */
    public function __construct(public string $domainName)
    {
        $fileManager = FileManager::default();
        $directory = $fileManager->url(SearchPathDirectory::libraryDirectory)->appendingPathComponent("Preferences");
        if (!$fileManager->fileExists($directory->path)) {
            $fileManager->createDirectory($directory, true);
        }
        $this->url = $directory->appendingPathComponent($this->domainName)->appendingPathExtension("plist");
        $this->dictionaryRepresentation = PropertyListSerialization::propertyListWithURL($this->url) ?? new Dictionary();
        $this->observer = NotificationCenter::default()->addObserverForName(UserDefaults::didChangeNotification, null, function (Notification $notification): void {
            /** @var UserDefaults $object */
            $object = $notification->object;
            if ($this === $object->applicationPreferences) {
                $bytes = PropertyListSerialization::writePropertyList($this->dictionaryRepresentation, $this->url);
                if ($bytes > USER_DEFAULTS_SIZE_LIMIT * BytesPerKilobyte) {
                    NotificationCenter::default()->postNotificationName(UserDefaults::sizeLimitExceededNotification, $object);
                }
            }
        });
    }

    public function invalidate(): void
    {
        NotificationCenter::default()->removeObserver($this->observer);
    }
}
