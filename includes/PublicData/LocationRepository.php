<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\PublicData;

use CornishPropertyIntelligence\Plugin;
use RuntimeException;

final class LocationRepository
{
    public function __construct(
        private readonly ManifestRepository $manifestRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function find(string $slug): array
    {
        $slug = sanitize_title($slug);

        if ($slug === '') {
            throw new RuntimeException('Location slug is missing.');
        }

        $settings = Plugin::settings();
        $payload = $this->manifestRepository->readVersionFile($settings['manifest_path'], 'locations/'.$slug.'.json');

        if (($payload['type'] ?? null) !== 'location') {
            throw new RuntimeException('Export file is not a location payload.');
        }

        return $payload;
    }
}
