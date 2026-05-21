<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\PublicData;

use CornishPropertyIntelligence\Safety\PayloadScanner;
use JsonException;
use RuntimeException;

final class JsonLoader
{
    public function __construct(
        private readonly PayloadScanner $scanner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function load(string $path): array
    {
        $path = trim($path);

        if ($path === '') {
            throw new RuntimeException('Manifest path is empty.');
        }

        if (! is_readable($path) || ! is_file($path)) {
            throw new RuntimeException('Manifest file is missing or unreadable.');
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException('Manifest file could not be read.');
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Manifest JSON is invalid: '.$exception->getMessage(), 0, $exception);
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Manifest JSON must be an object.');
        }

        $violations = $this->scanner->scan($payload);

        if ($violations !== []) {
            throw new RuntimeException('Manifest failed safety scan: '.$violations[0]['reason'].' at '.$violations[0]['path']);
        }

        return $payload;
    }
}
