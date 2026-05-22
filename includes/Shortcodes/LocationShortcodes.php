<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Shortcodes;

use CornishPropertyIntelligence\PublicData\LocationRepository;
use CornishPropertyIntelligence\Rendering\LocationRenderer;
use CornishPropertyIntelligence\Rendering\NoticeRenderer;
use Throwable;

final class LocationShortcodes
{
    public function __construct(
        private readonly LocationRepository $locations,
        private readonly LocationRenderer $renderer,
        private readonly NoticeRenderer $notices,
    ) {}

    public function register(): void
    {
        add_filter('query_vars', [$this, 'registerQueryVars']);

        add_shortcode('cp_location_title', [$this, 'title']);
        add_shortcode('cp_location_hero', [$this, 'hero']);
        add_shortcode('cp_location_summary', [$this, 'summary']);
        add_shortcode('cp_location_local_context', [$this, 'localContext']);
        add_shortcode('cp_location_modules', [$this, 'modules']);
        add_shortcode('cp_location_campaign_cta', [$this, 'campaignCta']);
        add_shortcode('cp_location_privacy_note', [$this, 'privacyNote']);
    }

    /**
     * @param array<int, string> $queryVars
     * @return array<int, string>
     */
    public function registerQueryVars(array $queryVars): array
    {
        $queryVars[] = 'cp_location_slug';

        return $queryVars;
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function title(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        if ($payload === null) {
            return $this->unavailable();
        }

        return $this->renderer->title($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function hero(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        if ($payload === null) {
            return $this->unavailable();
        }

        return $this->renderer->hero($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function summary(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        if ($payload === null) {
            return $this->unavailable();
        }

        return $this->renderer->summary($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function localContext(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        if ($payload === null) {
            return $this->unavailable();
        }

        return $this->renderer->localContext($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function modules(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        if ($payload === null) {
            return $this->unavailable();
        }

        return $this->renderer->modules($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function privacyNote(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        if ($payload === null) {
            return $this->unavailable();
        }

        return $this->renderer->privacyNote($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function campaignCta(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        if ($payload === null) {
            return '';
        }

        return $this->renderer->campaignCta($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     * @return array<string, mixed>|null
     */
    private function payload(array|string $attributes): ?array
    {
        $attributes = shortcode_atts(['slug' => ''], is_array($attributes) ? $attributes : [], 'cp_location');
        $slug = sanitize_title((string) $attributes['slug']);

        if ($slug === '') {
            $slug = $this->contextSlug();
        }

        try {
            return $this->locations->find($slug);
        } catch (Throwable) {
            return null;
        }
    }

    private function contextSlug(): string
    {
        $slug = get_query_var('cp_location_slug');

        if (! is_string($slug) || $slug === '') {
            $slug = isset($_GET['cp_location_slug']) ? (string) wp_unslash($_GET['cp_location_slug']) : '';
        }

        return sanitize_title($slug);
    }

    private function unavailable(): string
    {
        return $this->notices->render('Location data is not available yet.', 'cpi-location-unavailable');
    }
}
