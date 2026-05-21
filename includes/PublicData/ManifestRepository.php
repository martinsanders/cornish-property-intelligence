<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\PublicData;

use RuntimeException;

final class ManifestRepository
{
    public function __construct(
        private readonly JsonLoader $loader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function readRootManifest(string $path): array
    {
        $manifest = $this->loader->load($path);

        foreach (['current_version', 'generated_at', 'base_path', 'status'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $manifest)) {
                throw new RuntimeException('Manifest is missing required key: '.$requiredKey);
            }
        }

        if (($manifest['status'] ?? null) !== 'ready') {
            throw new RuntimeException('Manifest is not marked ready.');
        }

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function readVersionFile(string $manifestPath, string $relativePath): array
    {
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new RuntimeException('Export file path is not allowed.');
        }

        $manifest = $this->readRootManifest($manifestPath);
        $basePath = (string) ($manifest['base_path'] ?? '');

        if ($basePath === '' || str_contains($basePath, '..')) {
            throw new RuntimeException('Manifest base path is not allowed.');
        }

        $exportRoot = rtrim(dirname($manifestPath), DIRECTORY_SEPARATOR);
        $exportPath = $exportRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $basePath.$relativePath);

        $realExportRoot = realpath($exportRoot);
        $realExportPath = realpath($exportPath);

        if ($realExportRoot === false || $realExportPath === false || ! str_starts_with($realExportPath, $realExportRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Export JSON file is missing or unreadable.');
        }

        return $this->loader->load($realExportPath);
    }
}
