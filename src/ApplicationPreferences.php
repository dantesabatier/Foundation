<?php

namespace Sabatier\Foundation;

/** @internal */
readonly class ApplicationPreferences
{
    public URL $url;
    public Dictionary $dictionaryRepresentation;

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
        NotificationCenter::default()->addObserverForName(UserDefaults::didChangeNotification, null, function (Notification $notification): void {
            /** @var UserDefaults $object */
            $object = $notification->object;
            if ($this->dictionaryRepresentation->isEqual($object->dictionaryRepresentation())) {
                $bytes = PropertyListSerialization::writePropertyList($this->dictionaryRepresentation, $this->url);
                if ($bytes * 1024 > USER_DEFAULTS_SIZE_LIMIT) {
                    NotificationCenter::default()->postNotificationName(UserDefaults::sizeLimitExceededNotification, $object);
                }
            }
        });
    }
}
