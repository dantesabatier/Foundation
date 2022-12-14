<?php

namespace Sabatier\Foundation;

use DateTime;
use DOMDocument;
use DOMElement;
use DOMImplementation;
use DOMNode;
use JetBrains\PhpStorm\ExpectedValues;

/** @internal */
readonly class PropertyListSerializer
{
    private DOMDocument $document;

    /** @noinspection PhpUnhandledExceptionInspection */
    public function __construct()
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $implementation = new DOMImplementation();
        $documentType = $implementation->createDocumentType('plist', "-//Apple//DTD PLIST 1.0//EN", "https://www.apple.com/DTDs/PropertyList-1.0.dtd");
        $document->appendChild($documentType);
        $element = $document->createElement('plist');
        $document->appendChild($element);
        $this->document = $document;
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    private function append(mixed $obj, DOMElement $element): void
    {
        $document = $this->document;
        if (is_null($obj)) {
            $element->appendChild($document->createElement('string', (string)$obj));
        } elseif (is_string($obj)) {
            $element->appendChild($document->createElement('string', $obj));
        } elseif (is_bool($obj)) {
            $element->appendChild($document->createElement($obj ? 'true' : 'false'));
        } elseif (is_int($obj)) {
            $element->appendChild($document->createElement('integer', (string)$obj));
        } elseif (is_float($obj)) {
            $element->appendChild($document->createElement('real', (string)$obj));
        } elseif ($obj instanceof Date) {
            $element->appendChild($document->createElement('date', (string)$obj));
        } elseif ($obj instanceof ArrayClass || $obj instanceof Set || $obj instanceof Dictionary || is_array($obj)) {
            $parent = $document->createElement(($obj instanceof ArrayClass || $obj instanceof Set || (is_array($obj) && is_sequential($obj))) ? 'array' : 'dict');
            $element->appendChild($parent);
            foreach ($obj as $key => $value) {
                if (is_string($key)) {
                    $parent->appendChild($document->createElement('key', $key));
                }
                $this->append($value, $parent);
            }
        }
    }

    private function element(DOMNode $node): ?DOMElement
    {
        while ($node->nodeType === XML_TEXT_NODE) {
            if (!($node = $node->nextSibling)) {
                break;
            }
        }
        if ($node instanceof DOMElement) {
            return $node;
        }
        return null;
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    private function value(DOMElement $element): mixed
    {
        return match ($element->tagName) {
            'plist' => ($element->firstChild && ($e = $this->element($element->firstChild))) ? $this->value($e) : null,
            'array' => $this->array($element),
            'dict' => $this->dict($element),
            'integer' => (int)$element->nodeValue,
            'real' => (float)$element->nodeValue,
            'true', 'false' => filter_var($element->nodeName, FILTER_VALIDATE_BOOLEAN),
            'date' => new Date((new DateTime($element->nodeValue ?? 'now'))->getTimestamp()),
            default => $element->nodeValue,
        };
    }

    private function array(DOMElement $for): ArrayClass
    {
        $array = new ArrayClass();
        for ($node = $for->firstChild; $node !== null; $node = $node->nextSibling) {
            if ($node instanceof DOMElement) {
                $array->append($this->value($node));
            }
        }
        return $array;
    }

    private function dict(DOMElement $for): Dictionary
    {
        $dictionary = new Dictionary();
        for ($node = $for->firstChild; $node !== null; $node = $node->nextSibling) {
            if (!($node instanceof DOMElement) || ($node->tagName !== 'key') || (!$next = $node->nextSibling) || !($key = $node->nodeValue) || !($value = $this->element($next))) {
                continue;
            }
            $dictionary->setValueForKey($this->value($value), $key);
        }
        return $dictionary;
    }

    public function data(/** @noinspection PhpUnusedParameterInspection */ mixed $plist, PropertyListSerializationFormat $format = PropertyListSerializationFormat::xml, int $options = 0): string
    {
        $this->append($plist, $this->document->documentElement);
        return $this->document->saveXML();
    }

    public function writePropertyList(/** @noinspection PhpUnusedParameterInspection */ mixed $plist, URL $url, PropertyListSerializationFormat $format = PropertyListSerializationFormat::xml, int $options = 0): int
    {
        $this->append($plist, $this->document->documentElement);
        return (int)$this->document->save($url->path);
    }

    public function propertyList(/** @noinspection PhpUnusedParameterInspection */ string $data, #[ExpectedValues(flagsFromClass: PropertyListSerializationMutabilityOptions::class)] int $options = 0, PropertyListSerializationFormat &$format = null): mixed
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        $this->document->loadXML($data) ?: throw new InternalInconsistencyException();
        $value = $this->value($this->document->documentElement) ?? throw new InternalInconsistencyException();
        $format = PropertyListSerializationFormat::xml;
        return $value;
    }

    public function propertyListWithURL(URL $url, #[ExpectedValues(flagsFromClass: PropertyListSerializationMutabilityOptions::class)] int $options = 0, PropertyListSerializationFormat $format = PropertyListSerializationFormat::xml): mixed
    {
        if ($url->isFileURL) {
            $path = $url->path;
            $fileManager = FileManager::default();
            /** @noinspection PhpUnhandledExceptionInspection */
            if (($fileManager->fileExists($path)) && ($contents = $fileManager->contents($path))) {
                return $this->propertyList($contents, $options, $format);
            }
        }
        return null;
    }
}
