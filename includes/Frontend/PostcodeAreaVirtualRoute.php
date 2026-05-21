<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Frontend;

use CornishPropertyIntelligence\PublicData\PostcodeAreaRepository;
use Throwable;

final class PostcodeAreaVirtualRoute
{
    private const QUERY_VAR = 'cp_postcode_area_key';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $payload = null;

    private ?string $areaKey = null;

    public function __construct(
        private readonly PostcodeAreaRepository $postcodeAreas,
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
        add_rewrite_rule('^near-me/([^/]+)/?$', 'index.php?'.self::QUERY_VAR.'=$matches[1]', 'top');
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
        $areaKey = $this->areaKey();

        if ($areaKey === null) {
            return;
        }

        try {
            $this->postcodePayload($areaKey);
        } catch (Throwable) {
            status_header(404);
            get_header();
            echo $this->missingMarkup($areaKey);
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
        $areaKey = $this->areaKey();

        if ($areaKey === null) {
            return $parts;
        }

        try {
            $payload = $this->postcodePayload($areaKey);
            $title = (string) ($payload['seo']['meta_title'] ?? $payload['public_label'] ?? '');
            $parts['title'] = $title !== '' ? $title : 'Postcode area information';
        } catch (Throwable) {
            $parts['title'] = 'Postcode area information';
        }

        return $parts;
    }

    public function canonicalTag(): void
    {
        $areaKey = $this->areaKey();

        if ($areaKey === null) {
            return;
        }

        $key = $this->postcodeAreas->normaliseKey($areaKey);

        if ($key['url_key'] === '') {
            return;
        }

        echo '<link rel="canonical" href="'.esc_url(home_url('/near-me/'.$key['url_key'].'/')).'">'."\n";
    }

    private function routeMarkup(): string
    {
        ob_start();
        ?>
        <main class="cpi-virtual-page cpi-postcode-area-virtual-page">
            <header class="cpi-virtual-page__header">
                <p class="cpi-virtual-page__eyebrow">
                    <?php echo esc_html__('Near Me Intelligence', 'cornish-property-intelligence'); ?>
                </p>
                <h1 class="cpi-virtual-page__title">
                    <?php echo do_shortcode('[cp_postcode_area_title]'); ?>
                </h1>
            </header>

            <?php echo do_shortcode('[cp_postcode_area_summary]'); ?>

            <section class="cpi-virtual-page__section">
                <h2><?php echo esc_html__('Evidence modules', 'cornish-property-intelligence'); ?></h2>
                <?php echo do_shortcode('[cp_postcode_area_modules]'); ?>
            </section>

            <section class="cpi-virtual-page__section">
                <h2><?php echo esc_html__('Associated guides', 'cornish-property-intelligence'); ?></h2>
                <?php echo do_shortcode('[cp_postcode_area_guides]'); ?>
            </section>

            <section class="cpi-virtual-page__section cpi-virtual-page__note">
                <h2><?php echo esc_html__('Evidence note', 'cornish-property-intelligence'); ?></h2>
                <?php echo do_shortcode('[cp_postcode_area_privacy_note]'); ?>
            </section>
        </main>
        <?php

        return (string) ob_get_clean();
    }

    private function missingMarkup(string $areaKey): string
    {
        $key = $this->postcodeAreas->normaliseKey($areaKey);
        $publicKey = $key['public_key'] !== '' ? $key['public_key'] : $areaKey;

        ob_start();
        ?>
        <main class="cpi-virtual-page cpi-postcode-area-virtual-page">
            <section class="cpi-virtual-page__section cpi-notice">
                <p class="cpi-virtual-page__eyebrow">
                    <?php echo esc_html__('Near Me Intelligence', 'cornish-property-intelligence'); ?>
                </p>
                <h1><?php echo esc_html__('Postcode area information', 'cornish-property-intelligence'); ?></h1>
                <p>
                    <?php
                    echo esc_html(sprintf(
                        'The route for %s is ready, but no approved public postcode JSON export is available yet.',
                        $publicKey
                    ));
                    ?>
                </p>
                <p><?php echo esc_html__('This page fails closed until a public-safe postcode-area or postcode-district export exists.', 'cornish-property-intelligence'); ?></p>
            </section>
        </main>
        <?php

        return (string) ob_get_clean();
    }

    private function areaKey(): ?string
    {
        $areaKey = get_query_var(self::QUERY_VAR);

        if (! is_string($areaKey) || $areaKey === '') {
            return null;
        }

        $key = $this->postcodeAreas->normaliseKey($areaKey);

        return $key['url_key'] !== '' ? $key['url_key'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function postcodePayload(string $areaKey): array
    {
        if ($this->payload !== null && $this->areaKey === $areaKey) {
            return $this->payload;
        }

        $payload = $this->postcodeAreas->find($areaKey);

        $this->areaKey = $areaKey;
        $this->payload = $payload;

        return $payload;
    }
}
