<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Frontend;

use CornishPropertyIntelligence\PublicData\LocationRepository;
use Throwable;

final class LocationVirtualRoute
{
    private const QUERY_VAR = 'cp_location_slug';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $payload = null;

    private ?string $slug = null;

    public function __construct(
        private readonly LocationRepository $locations,
    ) {}

    public function register(): void
    {
        add_action('init', [$this, 'registerRewriteRule']);
        add_filter('query_vars', [$this, 'registerQueryVar']);
        add_action('template_redirect', [$this, 'render']);
        add_filter('document_title_parts', [$this, 'documentTitle']);
        add_action('wp_head', [$this, 'canonicalTag']);
    }

    public function registerRewriteRule(): void
    {
        add_rewrite_rule('^locations/([^/]+)/?$', 'index.php?'.self::QUERY_VAR.'=$matches[1]', 'top');
    }

    /**
     * @param array<int, string> $queryVars
     * @return array<int, string>
     */
    public function registerQueryVar(array $queryVars): array
    {
        $queryVars[] = self::QUERY_VAR;

        return $queryVars;
    }

    public function render(): void
    {
        $slug = $this->locationSlug();

        if ($slug === null) {
            return;
        }

        try {
            $this->locationPayload($slug);
        } catch (Throwable) {
            status_header(404);
            get_header();
            echo $this->notFoundMarkup($slug);
            get_footer();
            exit;
        }

        status_header(200);
        get_header();
        echo $this->routeMarkup();
        get_footer();
        exit;
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function documentTitle(array $parts): array
    {
        $slug = $this->locationSlug();

        if ($slug === null) {
            return $parts;
        }

        try {
            $payload = $this->locationPayload($slug);
            $title = (string) ($payload['seo']['meta_title'] ?? $payload['public_label'] ?? '');

            if ($title !== '') {
                $parts['title'] = $title;
            }
        } catch (Throwable) {
            $parts['title'] = 'Location not available';
        }

        return $parts;
    }

    public function canonicalTag(): void
    {
        $slug = $this->locationSlug();

        if ($slug === null) {
            return;
        }

        $canonical = home_url('/locations/'.$slug.'/');

        echo '<link rel="canonical" href="'.esc_url($canonical).'">'."\n";
    }

    private function routeMarkup(): string
    {
        ob_start();
        ?>
        <main class="cpi-virtual-page cpi-location-virtual-page">
            <header class="cpi-virtual-page__header">
                <p class="cpi-virtual-page__eyebrow">
                    <?php echo esc_html__('Location Intelligence', 'cornish-property-intelligence'); ?>
                </p>
                <h1 class="cpi-virtual-page__title">
                    <?php echo do_shortcode('[cp_location_title]'); ?>
                </h1>
            </header>

            <?php echo do_shortcode('[cp_location_summary]'); ?>

            <section class="cpi-virtual-page__section">
                <h2><?php echo esc_html__('Evidence modules', 'cornish-property-intelligence'); ?></h2>
                <?php echo do_shortcode('[cp_location_modules]'); ?>
            </section>

            <section class="cpi-virtual-page__section cpi-virtual-page__note">
                <h2><?php echo esc_html__('Evidence note', 'cornish-property-intelligence'); ?></h2>
                <?php echo do_shortcode('[cp_location_privacy_note]'); ?>
            </section>
        </main>
        <?php

        return (string) ob_get_clean();
    }

    private function notFoundMarkup(string $slug): string
    {
        ob_start();
        ?>
        <main class="cpi-virtual-page cpi-location-virtual-page">
            <section class="cpi-virtual-page__section cpi-notice">
                <p class="cpi-virtual-page__eyebrow">
                    <?php echo esc_html__('Location Intelligence', 'cornish-property-intelligence'); ?>
                </p>
                <h1><?php echo esc_html__('Location not available yet', 'cornish-property-intelligence'); ?></h1>
                <p><?php echo esc_html(sprintf('No approved public JSON export is available for "%s" yet.', $slug)); ?></p>
            </section>
        </main>
        <?php

        return (string) ob_get_clean();
    }

    private function locationSlug(): ?string
    {
        $slug = get_query_var(self::QUERY_VAR);

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $slug = sanitize_title($slug);

        return $slug !== '' ? $slug : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function locationPayload(string $slug): array
    {
        if ($this->payload !== null && $this->slug === $slug) {
            return $this->payload;
        }

        $payload = $this->locations->find($slug);

        $this->slug = $slug;
        $this->payload = $payload;

        return $payload;
    }
}
