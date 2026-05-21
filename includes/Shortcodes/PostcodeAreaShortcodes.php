<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Shortcodes;

use CornishPropertyIntelligence\PublicData\PostcodeAreaRepository;
use CornishPropertyIntelligence\Rendering\NoticeRenderer;
use CornishPropertyIntelligence\Rendering\PostcodeAreaRenderer;
use Throwable;

final class PostcodeAreaShortcodes
{
    private const QUERY_VAR = 'cp_postcode_area_key';

    public function __construct(
        private readonly PostcodeAreaRepository $postcodeAreas,
        private readonly PostcodeAreaRenderer $renderer,
        private readonly NoticeRenderer $notices,
    ) {}

    public function register(): void
    {
        add_filter('query_vars', [$this, 'registerQueryVars']);

        add_shortcode('cp_postcode_area_title', [$this, 'title']);
        add_shortcode('cp_postcode_area_summary', [$this, 'summary']);
        add_shortcode('cp_postcode_area_modules', [$this, 'modules']);
        add_shortcode('cp_postcode_area_guides', [$this, 'guides']);
        add_shortcode('cp_postcode_area_privacy_note', [$this, 'privacyNote']);
    }

    /**
     * @param array<int, string> $queryVars
     * @return array<int, string>
     */
    public function registerQueryVars(array $queryVars): array
    {
        $queryVars[] = self::QUERY_VAR;

        return $queryVars;
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function title(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        return $this->renderer->title($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function summary(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        return $this->renderer->summary($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function modules(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        return $this->renderer->modules($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function guides(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        return $this->renderer->guides($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function privacyNote(array|string $attributes = []): string
    {
        $payload = $this->payload($attributes);

        return $this->renderer->privacyNote($payload);
    }

    /**
     * @param array<string, mixed>|string $attributes
     * @return array<string, mixed>|null
     */
    private function payload(array|string $attributes): ?array
    {
        $attributes = shortcode_atts(['area' => ''], is_array($attributes) ? $attributes : [], 'cp_postcode_area');
        $area = (string) $attributes['area'];

        if (trim($area) === '') {
            $area = $this->contextArea();
        }

        try {
            return $this->postcodeAreas->find($area);
        } catch (Throwable) {
            return null;
        }
    }

    private function contextArea(): string
    {
        $area = get_query_var(self::QUERY_VAR);

        if (! is_string($area) || $area === '') {
            $area = isset($_GET[self::QUERY_VAR]) ? (string) wp_unslash($_GET[self::QUERY_VAR]) : '';
        }

        return $area;
    }

    private function unavailable(): string
    {
        return $this->notices->render('Postcode-area data is not available yet.', 'cpi-postcode-area-unavailable');
    }
}
