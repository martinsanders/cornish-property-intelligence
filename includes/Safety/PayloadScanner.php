<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Safety;

final class PayloadScanner
{
    /**
     * @var array<int, string>
     */
    private const UNSAFE_MARKERS = [
        'full_postcode',
        'postcode_full',
        'pcds',
        'pcd7',
        'pcd8',
        'raw_payload',
        'raw_rows',
        'raw_text',
        'source_hash',
        'source_reference',
        'source_url',
        'source_file_path',
        'certificate_number',
        'uprn',
        'paon',
        'saon',
        'transaction_id',
        'address',
        'street',
        'nearest',
        'radius',
        'exact_local',
        'exact local',
        'distance_km',
        'epc_records',
        'planning_activity_records',
        'building_activity_records',
        'land_registry_sales',
        'raw_records',
        'individual_records',
        'private_address',
        'property_address',
    ];

    /**
     * @param mixed $payload
     * @return array<int, array{path: string, reason: string}>
     */
    public function scan(mixed $payload): array
    {
        return $this->scanValue($payload, '$');
    }

    /**
     * @return array<int, array{path: string, reason: string}>
     */
    private function scanValue(mixed $value, string $path): array
    {
        $violations = [];

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $keyPath = $path.'.'.(string) $key;

                if (is_string($key) && $this->containsUnsafeMarker($key)) {
                    $violations[] = [
                        'path' => $keyPath,
                        'reason' => 'Unsafe key marker: '.$key,
                    ];
                }

                array_push($violations, ...$this->scanValue($child, $keyPath));
            }

            return $violations;
        }

        if (is_string($value)) {
            if ($this->looksLikeFullPostcode($value)) {
                $violations[] = [
                    'path' => $path,
                    'reason' => 'Full postcode-like value detected.',
                ];
            }

            if ($this->containsUnsafeMarker($value)) {
                $violations[] = [
                    'path' => $path,
                    'reason' => 'Unsafe value marker detected.',
                ];
            }
        }

        return $violations;
    }

    private function containsUnsafeMarker(string $value): bool
    {
        $normalised = strtolower($value);

        foreach (self::UNSAFE_MARKERS as $marker) {
            if (str_contains($normalised, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeFullPostcode(string $value): bool
    {
        return preg_match('/\b[A-Z]{1,2}\d{1,2}[A-Z]?\s*\d[A-Z]{2}\b/i', $value) === 1;
    }
}
