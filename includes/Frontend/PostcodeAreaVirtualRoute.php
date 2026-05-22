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
        add_filter('body_class', [$this, 'bodyClass']);
        add_action('wp_head', [$this, 'robotsMeta']);
        add_action('wp_head', [$this, 'canonicalTag']);
    }

    /**
     * @param array<int, string> $classes
     * @return array<int, string>
     */
    public function bodyClass(array $classes): array
    {
        if ($this->areaKey() !== null) {
            $classes[] = 'cpi-virtual-route';
            $classes[] = 'cpi-postcode-area-route';
        }

        return $classes;
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

        $seo = $this->seoMetadata($areaKey);
        $canonical = is_string($seo['canonical_url'] ?? null) ? $seo['canonical_url'] : '';

        if ($canonical === '') {
            return;
        }

        echo '<link rel="canonical" href="'.esc_url($canonical).'">'."\n";
    }

    public function robotsMeta(): void
    {
        $areaKey = $this->areaKey();

        if ($areaKey === null) {
            return;
        }

        $seo = $this->seoMetadata($areaKey);

        if (($seo['indexing'] ?? '') !== 'noindex_follow') {
            return;
        }

        echo '<meta name="robots" content="noindex,follow">'."\n";
    }

    private function routeMarkup(): string
    {
        $areaKey = $this->areaKey();
        $payload = $areaKey !== null ? $this->postcodePayload($areaKey) : [];
        $summary = do_shortcode('[cp_postcode_area_summary]');
        $modules = do_shortcode('[cp_postcode_area_modules]');
        $guides = do_shortcode('[cp_postcode_area_guides]');
        $privacyNote = do_shortcode('[cp_postcode_area_privacy_note]');
        $heroChips = $this->heroChips($payload);
        $heroActions = $this->heroActions($payload);

        ob_start();
        ?>
        <main class="cpi-virtual-page cpi-postcode-area-virtual-page">
            <header class="cpi-location-hero cpi-postcode-area-hero">
                <section class="cpi-location-hero-block cpi-postcode-area-hero-block">
                    <p class="cpi-virtual-page__eyebrow">
                        <?php echo esc_html__('Near Me Intelligence', 'cornish-property-intelligence'); ?>
                    </p>
                    <h1 class="cpi-virtual-page__title">
                        <?php echo do_shortcode('[cp_postcode_area_title]'); ?>
                    </h1>

                    <?php if ($summary !== '') : ?>
                        <?php echo $summary; ?>
                    <?php endif; ?>

                    <?php if ($heroChips !== '') : ?>
                        <?php echo $heroChips; ?>
                    <?php endif; ?>

                    <?php if ($heroActions !== '') : ?>
                        <?php echo $heroActions; ?>
                    <?php endif; ?>
                </section>
            </header>

            <?php if ($guides !== '') : ?>
                <section class="cpi-virtual-page__section cpi-location-context-section cpi-postcode-area-context-section">
                    <?php echo $guides; ?>
                </section>
            <?php endif; ?>

            <section class="cpi-virtual-page__section cpi-location-module-stack cpi-postcode-area-module-stack">
                <?php echo $modules; ?>
            </section>

            <?php if ($privacyNote !== '') : ?>
                <section class="cpi-virtual-page__section cpi-virtual-page__note">
                    <h2><?php echo esc_html__('Evidence note', 'cornish-property-intelligence'); ?></h2>
                    <?php echo $privacyNote; ?>
                </section>
            <?php endif; ?>
        </main>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function heroActions(array $payload): string
    {
        $guide = $this->associatedGuide($payload);

        ob_start();
        ?>
        <div class="cpi-location-hero-block__actions cpi-postcode-area-hero-block__actions">
            <?php if ($guide['url'] !== '') : ?>
                <a class="cpi-button cpi-button--primary wp-element-button" href="<?php echo esc_url($guide['url']); ?>">
                    <?php echo esc_html(sprintf('Open %s guide', $guide['title'] !== '' ? $guide['title'] : 'Location')); ?>
                </a>
            <?php endif; ?>
            <a class="cpi-button cpi-button--secondary wp-element-button" href="<?php echo esc_url(home_url('/locations/')); ?>">
                <?php echo esc_html__('All location insights', 'cornish-property-intelligence'); ?>
            </a>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function heroChips(array $payload): string
    {
        $chips = [];
        $geographyLevel = is_scalar($payload['geography_level'] ?? null) ? (string) $payload['geography_level'] : '';
        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
        $fallbackChain = is_array($payload['fallback_chain'] ?? null) ? $payload['fallback_chain'] : [];

        $chips[] = match ($geographyLevel) {
            'postcode_sector' => 'Postcode sector result',
            'postcode_district' => 'Postcode district result',
            default => 'Near Me result',
        };

        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }

            $moduleType = is_scalar($module['module_type'] ?? null) ? (string) $module['module_type'] : '';

            if ($moduleType === 'epc_status') {
                $chips[] = 'EPC / retrofit';
            }
        }

        if ($fallbackChain !== []) {
            $chips[] = 'Fallback context';
        }

        $chips = array_values(array_unique(array_filter($chips)));

        if ($chips === []) {
            return '';
        }

        ob_start();
        ?>
        <ul class="cpi-location-hero-block__chips cpi-postcode-area-hero-block__chips" aria-label="<?php echo esc_attr__('Evidence signals', 'cornish-property-intelligence'); ?>">
            <?php foreach ($chips as $chip) : ?>
                <li><?php echo esc_html($chip); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{title: string, url: string}
     */
    private function associatedGuide(array $payload): array
    {
        $fallbackChain = is_array($payload['fallback_chain'] ?? null) ? $payload['fallback_chain'] : [];

        foreach ($fallbackChain as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['level'] ?? '') !== 'associated_location_guide') {
                continue;
            }

            $title = is_scalar($item['label'] ?? null) ? trim((string) $item['label']) : '';
            $url = is_scalar($item['url'] ?? null) ? $this->normalisePublicUrl((string) $item['url']) : '';

            if ($title !== '' || $url !== '') {
                return [
                    'title' => $title,
                    'url' => $url,
                ];
            }
        }

        $guides = is_array($payload['associated_guides'] ?? null)
            ? $payload['associated_guides']
            : (is_array($payload['guides'] ?? null) ? $payload['guides'] : []);

        foreach ($guides as $guide) {
            if (! is_array($guide)) {
                continue;
            }

            $title = is_scalar($guide['title'] ?? $guide['label'] ?? null) ? trim((string) ($guide['title'] ?? $guide['label'])) : '';
            $url = is_scalar($guide['url'] ?? $guide['href'] ?? null) ? trim((string) ($guide['url'] ?? $guide['href'])) : '';
            $slug = is_scalar($guide['slug'] ?? null) ? sanitize_title((string) $guide['slug']) : '';

            if ($url === '' && $slug !== '') {
                $url = home_url('/locations/'.$slug.'/');
            }

            if ($title !== '' || $url !== '') {
                return [
                    'title' => $title,
                    'url' => $this->normalisePublicUrl($url),
                ];
            }
        }

        return [
            'title' => '',
            'url' => '',
        ];
    }

    private function normalisePublicUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $query = (string) wp_parse_url($url, PHP_URL_QUERY);

        if ($path === '/near-me/results' && $query !== '') {
            parse_str($query, $params);
            $area = isset($params['area']) && is_scalar($params['area']) ? sanitize_title((string) $params['area']) : '';

            return $area !== '' ? home_url('/near-me/'.$area.'/') : '';
        }

        if (str_starts_with($path, '/locations/')) {
            return home_url(trailingslashit($path));
        }

        if (str_starts_with($path, '/articles/')) {
            return home_url(trailingslashit($path));
        }

        if (str_starts_with($url, '/')) {
            return home_url($url);
        }

        return $url;
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

    /**
     * @return array{indexing: string, canonical_url: string, reason: string}
     */
    private function seoMetadata(string $areaKey): array
    {
        $canonical = '';
        $hasPayload = false;

        try {
            $payload = $this->postcodePayload($areaKey);
            $hasPayload = true;
            $canonical = $this->associatedLocationCanonical($payload);
        } catch (Throwable) {
            $canonical = '';
        }

        if ($canonical === '') {
            $key = $this->postcodeAreas->normaliseKey($areaKey);
            $canonical = $hasPayload && $key['url_key'] !== ''
                ? home_url('/near-me/'.$key['url_key'].'/')
                : home_url('/near-me/');
        }

        return [
            'indexing' => 'noindex_follow',
            'canonical_url' => $canonical,
            'reason' => 'Near Me postcode result routes are lookup pages with limited or fallback evidence by default.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function associatedLocationCanonical(array $payload): string
    {
        $chain = is_array($payload['fallback_chain'] ?? null) ? $payload['fallback_chain'] : [];

        foreach ($chain as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['level'] ?? '') !== 'associated_location_guide') {
                continue;
            }

            $canonical = $this->normaliseCanonicalUrl($item['url'] ?? '');

            if ($canonical !== '') {
                return $canonical;
            }
        }

        $guides = is_array($payload['associated_guides'] ?? null) ? $payload['associated_guides'] : [];

        foreach ($guides as $guide) {
            if (! is_array($guide)) {
                continue;
            }

            $canonical = $this->normaliseCanonicalUrl($guide['url'] ?? '');

            if ($canonical !== '') {
                return $canonical;
            }
        }

        return '';
    }

    private function normaliseCanonicalUrl(mixed $url): string
    {
        if (! is_scalar($url)) {
            return '';
        }

        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        $path = (string) wp_parse_url($url, PHP_URL_PATH);

        if ($path === '' && str_starts_with($url, '/')) {
            $path = $url;
        }

        if (! str_starts_with($path, '/locations/')) {
            return '';
        }

        return home_url(trailingslashit($path));
    }
}
