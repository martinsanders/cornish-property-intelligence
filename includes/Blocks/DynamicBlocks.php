<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Blocks;

use CornishPropertyIntelligence\PublicData\LocationRepository;
use CornishPropertyIntelligence\PublicData\PostcodeAreaRepository;
use CornishPropertyIntelligence\Rendering\LocationRenderer;
use CornishPropertyIntelligence\Rendering\NoticeRenderer;
use CornishPropertyIntelligence\Rendering\PostcodeAreaRenderer;
use Throwable;

final class DynamicBlocks
{
    private const CATEGORY = 'cornish-property';

    public function __construct(
        private readonly LocationRepository $locations,
        private readonly PostcodeAreaRepository $postcodeAreas,
        private readonly LocationRenderer $locationRenderer,
        private readonly PostcodeAreaRenderer $postcodeRenderer,
        private readonly NoticeRenderer $notices,
    ) {}

    public function register(): void
    {
        add_filter('block_categories_all', [$this, 'blockCategories']);
        add_action('init', [$this, 'registerBlocks']);
        add_action('init', [$this, 'registerPatterns']);
        add_action('enqueue_block_assets', [$this, 'enqueueBlockAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    public function blockCategories(array $categories): array
    {
        foreach ($categories as $category) {
            if (($category['slug'] ?? '') === self::CATEGORY) {
                return $categories;
            }
        }

        $categories[] = [
            'slug' => self::CATEGORY,
            'title' => __('Cornish Property', 'cornish-property-intelligence'),
            'icon' => null,
        ];

        return $categories;
    }

    public function registerBlocks(): void
    {
        $this->registerEditorScript();

        $shared = $this->sharedAttributes();

        $this->registerBlock('search', __('Cornish Property Search', 'cornish-property-intelligence'), 'search', __('Safe public search controls for Cornish Property pages.', 'cornish-property-intelligence'), [
            'searchMode' => ['type' => 'string', 'default' => 'near_me'],
            'labelText' => ['type' => 'string', 'default' => ''],
            'placeholderText' => ['type' => 'string', 'default' => ''],
            'buttonText' => ['type' => 'string', 'default' => ''],
            'showHint' => ['type' => 'boolean', 'default' => true],
            'textAlign' => ['type' => 'string', 'default' => ''],
        ], [$this, 'renderSearch']);

        $this->registerBlock('content', __('Cornish Property Content', 'cornish-property-intelligence'), 'text-page', __('Title, summary and evidence-note content from static public JSON.', 'cornish-property-intelligence'), [
            ...$shared,
            'contentType' => ['type' => 'string', 'default' => 'title_summary'],
            'headingLevel' => ['type' => 'number', 'default' => 2],
            'showKicker' => ['type' => 'boolean', 'default' => false],
            'showSummary' => ['type' => 'boolean', 'default' => true],
        ], [$this, 'renderContent']);

        $this->registerBlock('module', __('Cornish Property Module', 'cornish-property-intelligence'), 'analytics', __('Full module stack or one evidence module from the resolved public JSON payload.', 'cornish-property-intelligence'), [
            ...$shared,
            'displayMode' => ['type' => 'string', 'default' => 'stack'],
            'moduleType' => ['type' => 'string', 'default' => 'market'],
            'layoutVariant' => ['type' => 'string', 'default' => 'standard'],
            'showChart' => ['type' => 'boolean', 'default' => true],
            'showMetrics' => ['type' => 'boolean', 'default' => true],
            'showControls' => ['type' => 'boolean', 'default' => true],
            'showSupportingEvidence' => ['type' => 'boolean', 'default' => true],
            'showEvidenceNote' => ['type' => 'boolean', 'default' => true],
            'showMissingModules' => ['type' => 'boolean', 'default' => true],
        ], [$this, 'renderModule']);

        $this->registerBlock('evidence', __('Cornish Property Evidence', 'cornish-property-intelligence'), 'privacy', __('Fallback journey, module availability and public evidence boundary context.', 'cornish-property-intelligence'), [
            ...$shared,
            'evidenceView' => ['type' => 'string', 'default' => 'boundary_note'],
            'evidenceLayout' => ['type' => 'string', 'default' => 'journey'],
            'showAssociatedGuide' => ['type' => 'boolean', 'default' => true],
            'showAssociatedArticles' => ['type' => 'boolean', 'default' => true],
            'showMissingEvidenceNotes' => ['type' => 'boolean', 'default' => true],
            'showFallbackReason' => ['type' => 'boolean', 'default' => true],
        ], [$this, 'renderEvidence']);

    }

    public function registerPatterns(): void
    {
        if (! function_exists('register_block_pattern')) {
            return;
        }

        if (function_exists('register_block_pattern_category')) {
            register_block_pattern_category(self::CATEGORY, [
                'label' => __('Cornish Property', 'cornish-property-intelligence'),
            ]);
        }

        register_block_pattern('cornish-property/location-intelligence-template', [
            'title' => __('Location Intelligence Template', 'cornish-property-intelligence'),
            'categories' => [self::CATEGORY],
            'description' => __('Starter template using compact Cornish Property content, module and evidence blocks.', 'cornish-property-intelligence'),
            'content' => $this->patternContent([
                '<!-- wp:cornish-property/content {"dataSource":"route","contentType":"title_summary","headingLevel":1} /-->',
                '<!-- wp:cornish-property/module {"dataSource":"route","displayMode":"stack"} /-->',
                '<!-- wp:cornish-property/evidence {"dataSource":"route","evidenceView":"boundary_note","evidenceLayout":"inline"} /-->',
            ]),
        ]);

        register_block_pattern('cornish-property/near-me-result-template', [
            'title' => __('Near Me Result Template', 'cornish-property-intelligence'),
            'categories' => [self::CATEGORY],
            'description' => __('Starter template for public Near Me postcode result pages.', 'cornish-property-intelligence'),
            'content' => $this->patternContent([
                '<!-- wp:cornish-property/content {"dataSource":"route","contentType":"title_summary","headingLevel":1} /-->',
                '<!-- wp:cornish-property/search {"searchMode":"near_me"} /-->',
                '<!-- wp:cornish-property/module {"dataSource":"route","displayMode":"stack"} /-->',
                '<!-- wp:cornish-property/evidence {"dataSource":"route","evidenceView":"full_context","evidenceLayout":"journey"} /-->',
            ]),
        ]);

        register_block_pattern('cornish-property/near-me-landing-search-page', [
            'title' => __('Near Me Landing/Search Page', 'cornish-property-intelligence'),
            'categories' => [self::CATEGORY],
            'description' => __('Editorial landing page starter with a Cornish Property search block.', 'cornish-property-intelligence'),
            'content' => $this->patternContent([
                '<!-- wp:heading {"level":1} --><h1>Find postcode-area property intelligence</h1><!-- /wp:heading -->',
                '<!-- wp:paragraph --><p>Search by broad postcode district or sector to open the matching Near Me result page.</p><!-- /wp:paragraph -->',
                '<!-- wp:cornish-property/search {"searchMode":"near_me"} /-->',
                '<!-- wp:paragraph --><p>Results use approved public-safe aggregate evidence only.</p><!-- /wp:paragraph -->',
            ]),
        ]);

        register_block_pattern('cornish-property/location-hero-section', [
            'title' => __('Location Hero Section', 'cornish-property-intelligence'),
            'categories' => [self::CATEGORY],
            'description' => __('Compact editable hero starter for Location templates.', 'cornish-property-intelligence'),
            'content' => $this->patternContent([
                '<!-- wp:cornish-property/content {"dataSource":"route","contentType":"title_summary","headingLevel":1,"showKicker":true} /-->',
            ]),
        ]);

        register_block_pattern('cornish-property/near-me-hero-search-section', [
            'title' => __('Near Me Hero Search Section', 'cornish-property-intelligence'),
            'categories' => [self::CATEGORY],
            'description' => __('Compact editable hero starter for Near Me templates.', 'cornish-property-intelligence'),
            'content' => $this->patternContent([
                '<!-- wp:cornish-property/content {"dataSource":"route","contentType":"title_summary","headingLevel":1,"showKicker":true} /-->',
                '<!-- wp:cornish-property/search {"searchMode":"near_me"} /-->',
            ]),
        ]);
    }

    public function enqueueEditorAssets(): void
    {
        $this->registerEditorScript();

        wp_enqueue_script('cornish-property-intelligence-blocks');
    }

    public function enqueueBlockAssets(): void
    {
        if (! is_admin()) {
            return;
        }

        wp_enqueue_style(
            'cornish-property-intelligence-block-editor',
            CPI_PLUGIN_URL.'assets/frontend.css',
            [],
            $this->assetVersion('assets/frontend.css')
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderSearch(array $attributes): string
    {
        $mode = sanitize_key((string) ($attributes['searchMode'] ?? 'near_me'));

        if ($mode !== 'near_me') {
            return $this->placeholder('Location search is not available yet. Use Near Me postcode area search for this release.');
        }

        return $this->wrap($attributes, $this->postcodeRenderer->search(
            null,
            'block',
            $this->shortText($attributes['labelText'] ?? ''),
            $this->shortText($attributes['placeholderText'] ?? ''),
            $this->shortText($attributes['buttonText'] ?? ''),
            $this->boolAttribute($attributes, 'showHint', true),
        ), 'cpi-search-block');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderContent(array $attributes): string
    {
        $resolved = $this->resolvePayload($attributes);

        if ($resolved['payload'] === null) {
            return $this->placeholder('Content will render when route context, a Location slug or a postcode area key is available.');
        }

        $contentType = sanitize_key((string) ($attributes['contentType'] ?? 'title_summary'));
        $parts = [];

        if ($this->boolAttribute($attributes, 'showKicker', false)) {
            $parts[] = '<p class="cpi-virtual-page__eyebrow">'.esc_html($resolved['type'] === 'location' ? 'Location Intelligence' : 'Near Me').'</p>';
        }

        if (in_array($contentType, ['title', 'title_summary'], true)) {
            $title = $resolved['type'] === 'location'
                ? $this->locationRenderer->title($resolved['payload'])
                : $this->postcodeRenderer->title($resolved['payload']);
            $parts[] = $this->heading($attributes, $title, 'cpi-intelligence-block-title');
        }

        if (in_array($contentType, ['summary', 'title_summary'], true) && $this->boolAttribute($attributes, 'showSummary', true)) {
            $parts[] = $resolved['type'] === 'location'
                ? $this->locationRenderer->summary($resolved['payload'])
                : $this->postcodeRenderer->summary($resolved['payload']);
        }

        if ($contentType === 'evidence_note') {
            $parts[] = $resolved['type'] === 'location'
                ? $this->locationRenderer->privacyNote($resolved['payload'])
                : $this->postcodeRenderer->privacyNote($resolved['payload']);
        }

        return $this->wrap($attributes, implode('', $parts), 'cpi-content-block cpi-content-block--'.sanitize_html_class($contentType));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderModule(array $attributes): string
    {
        $resolved = $this->resolvePayload($attributes);

        if ($resolved['payload'] === null) {
            return $this->placeholder('Module content will render when route context, a Location slug or a postcode area key is available.');
        }

        $mode = sanitize_key((string) ($attributes['displayMode'] ?? 'stack'));
        $moduleType = $mode === 'single' ? $this->moduleType($attributes) : '';
        $modules = $resolved['type'] === 'location'
            ? $this->locationRenderer->modules($resolved['payload'], $moduleType)
            : $this->postcodeRenderer->modules($resolved['payload'], $moduleType);

        return $this->wrap($attributes, $modules, $this->moduleClasses($attributes, $mode));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderEvidence(array $attributes): string
    {
        $resolved = $this->resolvePayload($attributes);

        if ($resolved['payload'] === null) {
            return $this->placeholder('Evidence context will render when route context, a Location slug or a postcode area key is available.');
        }

        $view = sanitize_key((string) ($attributes['evidenceView'] ?? 'boundary_note'));
        $parts = [];

        if (in_array($view, ['boundary_note', 'full_context'], true)) {
            $parts[] = $resolved['type'] === 'location'
                ? $this->locationRenderer->privacyNote($resolved['payload'])
                : $this->postcodeRenderer->privacyNote($resolved['payload']);
        }

        if ($resolved['type'] === 'postcode' && in_array($view, ['fallback_journey', 'full_context'], true)) {
            $parts[] = $this->postcodeRenderer->guides($resolved['payload']);
        }

        if ($resolved['type'] === 'postcode' && in_array($view, ['module_availability', 'full_context'], true)) {
            $parts[] = $this->moduleAvailability($resolved['payload'], $attributes);
        }

        if ($parts === []) {
            return '';
        }

        $layout = $this->choice($attributes['evidenceLayout'] ?? '', ['journey', 'cards', 'inline'], 'journey');

        return $this->wrap($attributes, implode('', $parts), 'cpi-evidence-block cpi-evidence-block--'.sanitize_html_class($view).' cpi-evidence-block--layout-'.sanitize_html_class($layout));
    }

    /**
     * @return array<string, array{type: string, default: string}>
     */
    private function sharedAttributes(): array
    {
        return [
            'dataSource' => ['type' => 'string', 'default' => 'route'],
            'locationSlug' => ['type' => 'string', 'default' => ''],
            'areaKey' => ['type' => 'string', 'default' => ''],
            'textAlign' => ['type' => 'string', 'default' => ''],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{type: string, payload: array<string, mixed>|null}
     */
    private function resolvePayload(array $attributes): array
    {
        $source = sanitize_key((string) ($attributes['dataSource'] ?? 'route'));

        if ($source === 'location' || ($source === 'route' && $this->shortText($attributes['locationSlug'] ?? '') !== '')) {
            return ['type' => 'location', 'payload' => $this->locationPayload($attributes)];
        }

        if (in_array($source, ['postcode', 'postcode_area', 'near_me'], true) || ($source === 'route' && $this->shortText($attributes['areaKey'] ?? '') !== '')) {
            return ['type' => 'postcode', 'payload' => $this->postcodePayload($attributes)];
        }

        $locationSlug = $this->routeLocationSlug();

        if ($locationSlug !== '') {
            return ['type' => 'location', 'payload' => $this->locationPayload(['locationSlug' => $locationSlug])];
        }

        $areaKey = $this->routePostcodeAreaKey();

        if ($areaKey !== '') {
            return ['type' => 'postcode', 'payload' => $this->postcodePayload(['areaKey' => $areaKey])];
        }

        return ['type' => 'none', 'payload' => null];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function wrap(array $attributes, string $content, string $className): string
    {
        if ($content === '') {
            return '';
        }

        $classes = [
            'cpi-dynamic-block',
            ...array_filter(array_map('sanitize_html_class', explode(' ', $className))),
        ];
        $align = $this->choice($attributes['textAlign'] ?? '', ['', 'left', 'center', 'right'], '');

        if ($align !== '') {
            $classes[] = 'has-text-align-'.$align;
        }

        return '<div class="'.esc_attr(implode(' ', $classes)).'">'.$content.'</div>';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function moduleClasses(array $attributes, string $mode): string
    {
        $variant = $this->choice($attributes['layoutVariant'] ?? '', ['standard', 'compact', 'chart_first', 'metrics_first'], 'standard');
        $classes = [
            'cpi-module-block',
            'cpi-module-block--mode-'.sanitize_html_class($mode === 'single' ? 'single' : 'stack'),
            'cpi-module-block--layout-'.sanitize_html_class(str_replace('_', '-', $variant)),
        ];
        $toggles = [
            'showChart' => 'hide-chart',
            'showMetrics' => 'hide-metrics',
            'showControls' => 'hide-controls',
            'showSupportingEvidence' => 'hide-supporting-evidence',
            'showEvidenceNote' => 'hide-evidence-note',
            'showMissingModules' => 'hide-missing-modules',
        ];

        foreach ($toggles as $key => $class) {
            if (! $this->boolAttribute($attributes, $key, true)) {
                $classes[] = 'cpi-module-block--'.$class;
            }
        }

        return implode(' ', $classes);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $attributes
     */
    private function moduleAvailability(array $payload, array $attributes): string
    {
        $availability = is_array($payload['module_availability'] ?? null) ? $payload['module_availability'] : [];

        if ($availability === []) {
            return '';
        }

        ob_start();
        ?>
        <section class="cpi-module-availability">
            <h3><?php echo esc_html__('Evidence availability', 'cornish-property-intelligence'); ?></h3>
            <ul class="cpi-module-availability__list">
                <?php foreach ($availability as $key => $item) : ?>
                    <?php if (! is_array($item)) {
                        continue;
                    } ?>
                    <?php
                    $label = $this->shortText($item['label'] ?? $key);
                    $status = $this->shortText($item['status'] ?? 'not_ready');
                    $note = $this->shortText($item['note'] ?? $item['summary'] ?? '');

                    if (! $this->boolAttribute($attributes, 'showMissingEvidenceNotes', true) && ! in_array($status, ['available', 'active'], true)) {
                        continue;
                    }
                    ?>
                    <li class="cpi-module-availability__item cpi-module-availability__item--<?php echo esc_attr(sanitize_html_class($status)); ?>">
                        <strong><?php echo esc_html($label !== '' ? $label : ucwords(str_replace('_', ' ', (string) $key))); ?></strong>
                        <?php if ($note !== '') : ?>
                            <span><?php echo esc_html($note); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<int, string> $allowed
     */
    private function choice(mixed $value, array $allowed, string $default): string
    {
        $value = sanitize_key((string) $value);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function shortText(mixed $value): string
    {
        return sanitize_text_field(wp_strip_all_tags((string) $value));
    }

    private function registerEditorScript(): void
    {
        if (wp_script_is('cornish-property-intelligence-blocks', 'registered')) {
            return;
        }

        wp_register_script(
            'cornish-property-intelligence-blocks',
            CPI_PLUGIN_URL.'assets/blocks.js',
            ['wp-blocks', 'wp-components', 'wp-element', 'wp-block-editor', 'wp-i18n'],
            $this->assetVersion('assets/blocks.js'),
            true
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    private function locationPayload(array $attributes): ?array
    {
        $slug = sanitize_title((string) ($attributes['locationSlug'] ?? ''));

        if ($slug === '') {
            $slug = $this->routeLocationSlug();
        }

        if ($slug === '') {
            return null;
        }

        try {
            return $this->locations->find($slug);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    private function postcodePayload(array $attributes): ?array
    {
        $area = sanitize_title((string) ($attributes['areaKey'] ?? ''));

        if ($area === '') {
            $area = $this->routePostcodeAreaKey();
        }

        if ($area === '') {
            return null;
        }

        try {
            return $this->postcodeAreas->find($area);
        } catch (Throwable) {
            return null;
        }
    }

    private function routeLocationSlug(): string
    {
        $slug = get_query_var('cp_location_slug');

        return is_string($slug) ? sanitize_title($slug) : '';
    }

    private function routePostcodeAreaKey(): string
    {
        $area = get_query_var('cp_postcode_area_key');

        return is_string($area) ? sanitize_title($area) : '';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function heading(array $attributes, string $content, string $className): string
    {
        $level = (int) ($attributes['headingLevel'] ?? 2);
        $level = min(6, max(1, $level));

        return '<h'.$level.' class="'.esc_attr($className).'">'.$content.'</h'.$level.'>';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function boolAttribute(array $attributes, string $key, bool $default): bool
    {
        return is_bool($attributes[$key] ?? null) ? $attributes[$key] : $default;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function moduleType(array $attributes): string
    {
        $moduleType = sanitize_key((string) ($attributes['moduleType'] ?? 'market'));

        return $moduleType === 'epc' ? 'epc_status' : ($moduleType ?: 'market');
    }

    private function placeholder(string $message): string
    {
        return $this->notices->render($message, 'cpi-dynamic-block-placeholder');
    }

    /**
     * @param array<string, array{type: string, default: mixed}> $attributes
     * @param callable $callback
     */
    private function registerBlock(string $name, string $title, string $icon, string $description, array $attributes, callable $callback): void
    {
        register_block_type('cornish-property/'.$name, [
            'api_version' => 3,
            'title' => $title,
            'category' => self::CATEGORY,
            'icon' => $icon,
            'description' => $description,
            'editor_script' => 'cornish-property-intelligence-blocks',
            'attributes' => $attributes,
            'render_callback' => $callback,
        ]);
    }

    /**
     * @param array<int, string> $blocks
     */
    private function patternContent(array $blocks): string
    {
        return implode("\n\n", $blocks);
    }

    private function assetVersion(string $path): string
    {
        $file = CPI_PLUGIN_DIR.$path;

        return is_readable($file) ? (string) filemtime($file) : '0.1.0';
    }
}
