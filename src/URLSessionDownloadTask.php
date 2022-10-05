<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 08/05/20
 * Time: 13:13
 */

namespace Sabatier\Foundation;

use Closure;

/**
 * Class URLSessionDownloadTask
 * A URL session task that stores downloaded data to a file.
 * @package Sabatier\Foundation
 */
class URLSessionDownloadTask extends URLSessionTask
{
    /**
     * Creates a download task that retrieves the contents of a URL based on the specified URL request object and saves the results to a file.
     * @param URLRequest $request A URL request object that provides the URL, cache policy, request type, body data or body stream, and so on.
     * @param Closure(URL|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     */
    public static function downloadTaskWithRequest(URLRequest $request, Closure $completion): URLSessionDownloadTask
    {
        return static::taskWithRequest($request, function (?string $data, ?URLResponse $response, ?Error $error) use ($completion): void {
            if ($response instanceof HTTPURLResponse) {
                $fileManager = FileManager::default();
                $directoryURL = $fileManager->temporaryDirectory;
                if (!($filename = $response->url->lastPathComponent)) {
                    $filename = uniqid((string)(new SystemRandomNumberGenerator())->next(), true);
                }
                if (!($pathExtension = $response->url->pathExtension) && ($mimeType = $response->mimeType) && ($extension = URLFileTypeMappings::shared()->preferredExtension($mimeType))) {
                    $pathExtension = $extension;
                }
                $url = $directoryURL->appendingPathComponent($filename)->appendPathExtension($pathExtension);
                $path = $url->path;
                $fileManager->createFile($path, $data);
                $completion($url, $response, $error);
                if ($fileManager->fileExists($path)) {
                    $fileManager->removeItem($url);
                }
                return;
            }
            $completion(null, $response, $error);
        });
    }
}
