<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\PublicData;

use CornishPropertyIntelligence\Plugin;
use RuntimeException;

final class PostcodeAreaRepository
{
    public function __construct(
        private readonly ManifestRepository $manifestRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function find(string $areaKey): array
    {
        $key = $this->normaliseKey($areaKey);

        if ($key['url_key'] === '') {
            throw new RuntimeException('Postcode area key is missing.');
        }

        $settings = Plugin::settings();
        $paths = $key['is_sector']
            ? [
                'postcode-areas/'.$key['url_key'].'.json',
                'postcode-districts/'.$key['district_key'].'.json',
            ]
            : [
                'postcode-districts/'.$key['district_key'].'.json',
            ];

        $lastException = null;

        foreach ($paths as $path) {
            try {
                $payload = $this->manifestRepository->readVersionFile($settings['manifest_path'], $path);
            } catch (RuntimeException $exception) {
                $lastException = $exception;
                continue;
            }

            if (! $this->isPostcodePayload($payload)) {
                throw new RuntimeException('Export file is not a postcode-area payload.');
            }

            return $payload;
        }

        throw new RuntimeException('No approved public postcode JSON export is available.', 0, $lastException);
    }

    /**
     * @return array{url_key: string, public_key: string, district_key: string, is_sector: bool}
     */
    public function normaliseKey(string $areaKey): array
    {
        $areaKey = strtolower(trim($areaKey));
        $areaKey = preg_replace('/[^a-z0-9-]+/', '-', $areaKey) ?? '';
        $areaKey = trim(preg_replace('/-+/', '-', $areaKey) ?? '', '-');

        if ($areaKey === '') {
            return [
                'url_key' => '',
                'public_key' => '',
                'district_key' => '',
                'is_sector' => false,
            ];
        }

        $parts = explode('-', $areaKey);
        $district = sanitize_title((string) ($parts[0] ?? ''));
        $sector = sanitize_title((string) ($parts[1] ?? ''));
        $isSector = $district !== '' && $sector !== '';
        $urlKey = $isSector ? $district.'-'.$sector : $district;
        $publicKey = strtoupper($district).($isSector ? ' '.strtoupper($sector) : '');

        return [
            'url_key' => $urlKey,
            'public_key' => $publicKey,
            'district_key' => $district,
            'is_sector' => $isSector,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isPostcodePayload(array $payload): bool
    {
        $type = (string) ($payload['type'] ?? '');

        return in_array($type, [
            'postcode_area',
            'postcode-area',
            'postcode_sector',
            'postcode-sector',
            'postcode_district',
            'postcode-district',
        ], true);
    }
}
