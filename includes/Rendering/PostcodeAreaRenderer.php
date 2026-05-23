<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Rendering;

final class PostcodeAreaRenderer
{
    public function __construct(
        private readonly ModuleRenderer $modules,
        private readonly NoticeRenderer $notices,
    ) {}

    /**
     * @param array<string, mixed>|null $payload
     */
    public function title(?array $payload): string
    {
        if ($payload === null) {
            return '<span class="cpi-postcode-area-title">'.esc_html__('Postcode area information', 'cornish-property-intelligence').'</span>';
        }

        $title = $this->text($payload['public_label'] ?? $payload['title'] ?? $payload['seo']['meta_title'] ?? '');

        return '<span class="cpi-postcode-area-title">'.esc_html($title !== '' ? $title : 'Postcode area information').'</span>';
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function summary(?array $payload): string
    {
        if ($payload === null) {
            return $this->notices->render(
                'This route is ready for approved public-safe postcode intelligence, but no postcode JSON export is available yet.',
                'cpi-postcode-area-unavailable'
            );
        }

        $summary = $this->text($payload['summary'] ?? '');
        $intro = is_array($payload['intro'] ?? null) ? $payload['intro'] : [];
        $heading = $this->text($intro['heading'] ?? '');
        $body = $this->text($intro['body'] ?? '');

        if ($summary === '' && $heading === '' && $body === '') {
            return '';
        }

        ob_start();
        ?>
        <section class="cpi-summary cpi-postcode-area-summary">
            <?php if ($summary !== '') : ?>
                <p class="cpi-summary__lead"><?php echo esc_html($summary); ?></p>
            <?php endif; ?>
            <?php if ($heading !== '') : ?>
                <h2 class="cpi-summary__heading"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ($body !== '') : ?>
                <p><?php echo esc_html($body); ?></p>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function modules(?array $payload): string
    {
        if ($payload === null) {
            return '';
        }

        $modules = $this->normaliseModules($payload);
        $modules = $this->withExecutiveAnswerModule($modules, $payload);
        $modules = $this->withAvailabilityModules($modules, $payload);

        return $this->modules->render($this->sortModules($modules), 'cpi-location');
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function guides(?array $payload): string
    {
        if ($payload === null) {
            return '';
        }

        $chain = is_array($payload['fallback_chain'] ?? null) ? $payload['fallback_chain'] : [];
        $fallbackContext = is_array($payload['fallback_context'] ?? null) ? $payload['fallback_context'] : [];
        $guides = is_array($payload['associated_guides'] ?? null)
            ? $payload['associated_guides']
            : (is_array($payload['guides'] ?? null) ? $payload['guides'] : []);

        $articles = is_array($payload['associated_articles'] ?? null) ? $payload['associated_articles'] : [];
        $fallbackCards = $chain !== []
            ? $this->fallbackCardsFromChain($chain)
            : $this->fallbackContextCards($payload, $guides, $articles);
        $primaryGuide = $this->primaryGuideCard($fallbackCards);
        $fallbackReason = $this->text($fallbackContext['fallback_reason'] ?? '')
            ?: 'Near Me pages use the most local approved aggregate evidence first, then widen the context only where the export says a broader public guide is useful.';

        ob_start();
        ?>
        <section class="cpi-postcode-area-fallback-context cpi-location-local-context" aria-label="<?php echo esc_attr__('Fallback evidence context', 'cornish-property-intelligence'); ?>">
            <article class="cpi-location-local-context__main cpi-postcode-area-fallback-context__main">
                <p class="cpi-virtual-page__eyebrow"><?php echo esc_html__('Local context', 'cornish-property-intelligence'); ?></p>
                <h2><?php echo esc_html__('Postcode evidence context', 'cornish-property-intelligence'); ?></h2>
                <p><?php echo esc_html($fallbackReason); ?></p>
            </article>

            <?php if ($primaryGuide !== null) : ?>
                <aside class="cpi-location-local-context__note cpi-postcode-area-associated-guide-panel">
                    <p class="cpi-virtual-page__eyebrow"><?php echo esc_html__('Associated public guide', 'cornish-property-intelligence'); ?></p>
                    <h3><?php echo esc_html($primaryGuide['title']); ?></h3>
                    <?php if ($primaryGuide['body'] !== '') : ?>
                        <p><?php echo esc_html($primaryGuide['body']); ?></p>
                    <?php endif; ?>
                    <?php if ($primaryGuide['modules'] !== []) : ?>
                        <ul class="cpi-location-hero-block__chips cpi-postcode-area-guide-chips" aria-label="<?php echo esc_attr__('Guide modules', 'cornish-property-intelligence'); ?>">
                            <?php foreach ($primaryGuide['modules'] as $module) : ?>
                                <li><?php echo esc_html($this->moduleLabel($module)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($primaryGuide['url'] !== '') : ?>
                        <a class="cpi-button cpi-button--secondary wp-element-button" href="<?php echo esc_url($primaryGuide['url']); ?>"><?php echo esc_html__('Open Location guide', 'cornish-property-intelligence'); ?></a>
                    <?php endif; ?>
                </aside>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function privacyNote(?array $payload): string
    {
        if ($payload === null) {
            return '<p class="cpi-postcode-area-privacy-note cpi-evidence-note">'.esc_html__('This page fails closed until a public-safe postcode-area or postcode-district export exists.', 'cornish-property-intelligence').'</p>';
        }

        $note = $this->text($payload['privacy_note'] ?? $payload['evidence_note'] ?? '');

        if ($note === '') {
            $note = 'This view uses approved public-safe postcode-area intelligence only.';
        }

        return '<p class="cpi-postcode-area-privacy-note cpi-evidence-note">'.esc_html($note).'</p>';
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function search(?array $payload, string $context = 'inline'): string
    {
        $context = sanitize_html_class($context !== '' ? $context : 'inline');
        $identifier = wp_unique_id('cpi_near_me_search_');
        $label = $payload !== null
            ? 'Search another postcode area'
            : 'Search by postcode area';
        $hint = 'Use a broad postcode area such as TR15, TR15 2 or TR15-2.';

        ob_start();
        ?>
        <section class="cpi-near-me-search cpi-near-me-search--<?php echo esc_attr($context); ?>" aria-label="<?php echo esc_attr__('Near Me postcode area search', 'cornish-property-intelligence'); ?>">
            <div class="cpi-near-me-search__copy">
                <p class="cpi-near-me-search__label"><?php echo esc_html($label); ?></p>
                <p class="cpi-near-me-search__hint"><?php echo esc_html($hint); ?></p>
            </div>
            <form class="cpi-near-me-search__form" action="<?php echo esc_url(home_url('/near-me/')); ?>" method="get" data-cpi-near-me-search data-cpi-near-me-base="<?php echo esc_url(home_url('/near-me/')); ?>" novalidate>
                <label class="screen-reader-text" for="<?php echo esc_attr($identifier); ?>"><?php echo esc_html__('Postcode district or sector', 'cornish-property-intelligence'); ?></label>
                <input
                    id="<?php echo esc_attr($identifier); ?>"
                    class="cpi-near-me-search__input"
                    type="text"
                    inputmode="text"
                    autocomplete="postal-code"
                    autocapitalize="characters"
                    spellcheck="false"
                    placeholder="<?php echo esc_attr__('TR15 2', 'cornish-property-intelligence'); ?>"
                    data-cpi-near-me-search-input
                >
                <button class="cpi-button cpi-button--primary cpi-near-me-search__button wp-element-button" type="submit">
                    <?php echo esc_html__('Search', 'cornish-property-intelligence'); ?>
                </button>
            </form>
            <p class="cpi-near-me-search__message" data-cpi-near-me-search-message role="status" aria-live="polite"></p>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int|string, mixed>
     */
    private function normaliseModules(array $payload): array
    {
        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
        $normalised = [];

        foreach ($modules as $key => $module) {
            $normalised[$key] = is_array($module)
                ? $this->normaliseModule($module, $payload)
                : $module;
        }

        return $normalised;
    }

    /**
     * @param array<string, mixed> $module
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normaliseModule(array $module, array $payload): array
    {
        $moduleType = $this->text($module['module_type'] ?? '');
        $geography = $this->geographyLabel($payload, $module);

        if ($geography !== '' && $this->text($module['geography_label'] ?? '') === '') {
            $module['geography_label'] = $geography;
        }

        if (! is_array($module['interactive_charts'] ?? null)) {
            $interactiveCharts = $this->interactiveChartsFromStaticCharts(
                is_array($module['charts'] ?? null) ? $module['charts'] : [],
                $moduleType
            );

            if ($interactiveCharts !== []) {
                $module['interactive_charts'] = $interactiveCharts;
            }
        }

        if (is_array($module['interactive_charts'] ?? null)) {
            $controlTitle = $moduleType === 'epc_status'
                ? 'EPC aggregate evidence'
                : 'Postcode evidence controls';

            if (! is_array($module['data_studio_control'] ?? null)) {
                $module['data_studio_control'] = [
                    'title' => $controlTitle,
                    'summary' => 'Current view: approved aggregate evidence',
                    'coverage' => $this->text($module['coverage_note'] ?? ''),
                ];
            }

            if ($moduleType === 'epc_status') {
                $module['data_studio_control']['title'] = 'EPC rating controls';
                $module['data_studio_control']['summary'] = 'Current rating distribution';

                if (! is_array($module['data_studio_control']['groups'] ?? null)) {
                    $module['data_studio_control']['groups'] = [[
                        'key' => 'epc_view',
                        'label' => 'Evidence view',
                        'options' => [[
                            'value' => 'Current rating distribution',
                            'label' => 'Current rating distribution',
                            'active' => true,
                        ]],
                    ]];
                }
            }

            if ($this->text($module['current_view_summary'] ?? '') === '') {
                $module['current_view_summary'] = $moduleType === 'epc_status'
                    ? 'Current rating distribution'
                    : 'Current view: approved aggregate evidence';
            }

            if ($this->text($module['coverage_line'] ?? '') === '') {
                $module['coverage_line'] = $this->text($module['coverage_note'] ?? '');
            }

            if (! is_array($module['supporting_evidence'] ?? null)) {
                $supportingEvidence = $this->supportingEvidenceFromMetrics($module, $payload);

                if ($supportingEvidence !== []) {
                    $module['supporting_evidence'] = $supportingEvidence;
                }
            }
        }

        return $module;
    }

    /**
     * @param array<int|string, mixed> $modules
     * @param array<string, mixed> $payload
     * @return array<int|string, mixed>
     */
    private function withExecutiveAnswerModule(array $modules, array $payload): array
    {
        foreach ($modules as $key => $module) {
            $moduleType = is_array($module)
                ? $this->text($module['module_type'] ?? $key)
                : (string) $key;

            if ($moduleType === 'executive_answer') {
                return $modules;
            }
        }

        $summary = $this->text($payload['summary'] ?? '');
        $fallbackContext = is_array($payload['fallback_context'] ?? null) ? $payload['fallback_context'] : [];
        $fallbackReason = $this->text($fallbackContext['fallback_reason'] ?? '');

        $modules = ['executive_answer' => [
            'module_type' => 'executive_answer',
            'title' => 'What the evidence suggests',
            'headline' => $summary !== '' ? $summary : $fallbackReason,
        ]] + $modules;

        return $modules;
    }

    /**
     * @param array<int|string, mixed> $modules
     * @param array<string, mixed> $payload
     * @return array<int|string, mixed>
     */
    private function withAvailabilityModules(array $modules, array $payload): array
    {
        $availability = is_array($payload['module_availability'] ?? null) ? $payload['module_availability'] : [];

        if ($availability === []) {
            return $modules;
        }

        $presentTypes = [];

        foreach ($modules as $key => $module) {
            $moduleType = is_array($module)
                ? $this->displayModuleType($this->text($module['module_type'] ?? $key))
                : $this->displayModuleType((string) $key);

            if ($moduleType !== '') {
                $presentTypes[] = $moduleType;
            }
        }

        $presentTypes = array_unique($presentTypes);
        $reportedTypes = [];

        foreach ($availability as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sourceType = $this->text($item['module_type'] ?? '');
            $moduleType = $this->displayModuleType($sourceType);
            $status = $this->text($item['status'] ?? 'missing');

            if (
                $moduleType === ''
                || in_array($moduleType, $presentTypes, true)
                || in_array($moduleType, $reportedTypes, true)
                || in_array($status, ['active', 'available'], true)
            ) {
                continue;
            }

            $placeholder = $this->availabilityModule($item, $moduleType);

            if ($placeholder === []) {
                continue;
            }

            $modules['missing_'.$moduleType] = $placeholder;
            $reportedTypes[] = $moduleType;
        }

        foreach ($this->defaultMissingModuleTypes() as $moduleType) {
            if (in_array($moduleType, $presentTypes, true) || in_array($moduleType, $reportedTypes, true)) {
                continue;
            }

            $placeholder = $this->missingModule($moduleType);

            if ($placeholder === []) {
                continue;
            }

            $modules['missing_'.$moduleType] = $placeholder;
            $reportedTypes[] = $moduleType;
        }

        return $modules;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function availabilityModule(array $item, string $moduleType): array
    {
        $status = $this->text($item['status'] ?? 'missing');
        $sourceLevel = $this->text($item['source_level'] ?? 'none');
        $explanation = $this->text($item['explanation'] ?? '');
        $label = $this->text($item['label'] ?? '') ?: $this->moduleLabel($moduleType);
        $summary = $explanation !== ''
            ? $explanation
            : 'Approved aggregate evidence is not available for this postcode geography yet.';

        return [
            'module_type' => $moduleType,
            'title' => $label,
            'headline' => $summary,
            'body' => $this->statusLabel($status).'. '.$this->sourceLevelLabel($sourceLevel).'.',
            'supporting_evidence' => [[
                'key' => 'availability',
                'label' => 'Evidence availability',
                'summary' => $summary,
            ]],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function defaultMissingModuleTypes(): array
    {
        return [
            'market',
            'trade_work_activity',
            'change_mix',
            'opportunity_signals',
            'published_articles',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function missingModule(string $moduleType): array
    {
        $label = $this->moduleLabel($moduleType);
        $summary = 'Approved aggregate '.$this->moduleAvailabilityLabel($moduleType).' evidence is not available for this postcode geography yet.';

        return [
            'module_type' => $moduleType,
            'title' => $label,
            'headline' => $summary,
            'body' => 'Not available yet. Source level: none yet.',
            'supporting_evidence' => [[
                'key' => 'availability',
                'label' => 'Evidence availability',
                'summary' => $summary,
            ]],
        ];
    }

    private function moduleAvailabilityLabel(string $moduleType): string
    {
        return match ($moduleType) {
            'trade_work_activity' => 'trade / work activity',
            'change_mix' => 'change mix',
            'opportunity_signals' => 'opportunity signals',
            'published_articles' => 'published articles',
            default => strtolower($this->moduleLabel($moduleType)),
        };
    }

    private function displayModuleType(string $moduleType): string
    {
        return match ($moduleType) {
            'planning',
            'building_activity',
            'planning_building',
            'building_control',
            'competent_person' => 'trade_work_activity',
            default => $moduleType,
        };
    }

    /**
     * @param array<int|string, mixed> $modules
     * @return array<int|string, mixed>
     */
    private function sortModules(array $modules): array
    {
        $order = [
            'executive_answer' => 10,
            'market' => 20,
            'trade_work_activity' => 30,
            'epc_status' => 40,
            'change_mix' => 50,
            'opportunity_signals' => 60,
            'published_articles' => 70,
        ];

        uksort($modules, function (int|string $left, int|string $right) use ($modules, $order): int {
            $leftType = is_array($modules[$left] ?? null)
                ? $this->displayModuleType($this->text($modules[$left]['module_type'] ?? $left))
                : $this->displayModuleType((string) $left);
            $rightType = is_array($modules[$right] ?? null)
                ? $this->displayModuleType($this->text($modules[$right]['module_type'] ?? $right))
                : $this->displayModuleType((string) $right);

            return ($order[$leftType] ?? 100) <=> ($order[$rightType] ?? 100);
        });

        return $modules;
    }

    /**
     * @param array<int, mixed> $charts
     * @return array<int, array<string, mixed>>
     */
    private function interactiveChartsFromStaticCharts(array $charts, string $moduleType = ''): array
    {
        $interactiveCharts = [];

        foreach ($charts as $chart) {
            if (! is_array($chart)) {
                continue;
            }

            $interactiveChart = $this->distributionFromChart($chart, $moduleType);

            if ($interactiveChart !== null) {
                $interactiveCharts[] = $interactiveChart;
            }
        }

        return $interactiveCharts;
    }

    /**
     * @param array<string, mixed> $chart
     * @return array<string, mixed>|null
     */
    private function distributionFromChart(array $chart, string $moduleType = ''): ?array
    {
        $type = $this->text($chart['type'] ?? '');
        $labels = is_array($chart['labels'] ?? null) ? $chart['labels'] : [];
        $series = is_array($chart['series'] ?? null) ? $chart['series'] : [];
        $firstSeries = is_array($series[0] ?? null) ? $series[0] : [];
        $values = is_array($firstSeries['values'] ?? null) ? $firstSeries['values'] : [];

        if (! in_array($type, ['bar', 'grouped_bar'], true) || $labels === [] || $values === []) {
            return null;
        }

        $items = [];

        foreach ($labels as $index => $label) {
            $value = $values[$index] ?? null;
            $label = $this->text($label);

            if ($label === '' || ! is_numeric($value)) {
                continue;
            }

            $items[] = [
                'label' => $label,
                'value' => $value + 0,
            ];
        }

        if ($items === []) {
            return null;
        }

        if ($moduleType === 'epc_status') {
            return [
                'type' => 'rating-comparison',
                'title' => 'Current EPC rating profile',
                'description' => $this->epcChartDescription($this->text($chart['description'] ?? ''), $moduleType),
                'payload' => [
                    'categories' => array_map(fn (array $item): string => $this->epcRatingLabel((string) $item['label']), $items),
                    'series' => [[
                        'name' => 'Current EPC rating',
                        'data' => array_map(fn (array $item): int|float => $item['value'], $items),
                    ]],
                ],
            ];
        }

        return [
            'type' => 'distribution',
            'title' => $this->epcChartTitle($this->text($chart['title'] ?? ''), $moduleType),
            'description' => $this->epcChartDescription($this->text($chart['description'] ?? ''), $moduleType),
            'payload' => [
                'seriesName' => $this->text($firstSeries['name'] ?? 'Aggregate records'),
                'items' => $items,
            ],
        ];
    }

    private function epcRatingLabel(string $label): string
    {
        return str_starts_with($label, 'Rating ') ? $label : 'Rating '.$label;
    }

    private function epcChartTitle(string $title, string $moduleType): string
    {
        if ($moduleType !== 'epc_status') {
            return $title;
        }

        return str_contains(strtolower($title), 'prototype')
            ? 'Current EPC rating profile'
            : $title;
    }

    private function epcChartDescription(string $description, string $moduleType): string
    {
        if ($moduleType !== 'epc_status') {
            return $description;
        }

        return str_contains(strtolower($description), 'prototype')
            ? 'Approved aggregate EPC rating buckets only.'
            : $description;
    }

    /**
     * @param array<string, mixed> $module
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function supportingEvidenceFromMetrics(array $module, array $payload): array
    {
        $metrics = is_array($module['metrics'] ?? null) ? $module['metrics'] : [];

        if ($metrics === []) {
            return [];
        }

        $period = is_array($module['period'] ?? null) ? $module['period'] : [];
        $periodLabel = $this->text($period['label'] ?? '');
        $summary = $periodLabel !== ''
            ? $periodLabel
            : $this->geographyLabel($payload, $module);

        return [[
            'key' => $this->text($module['module_type'] ?? '') === 'epc_status' ? 'epc_summary' : 'current_period',
            'label' => $this->text($module['module_type'] ?? '') === 'epc_status' ? 'EPC summary' : 'Evidence summary',
            'summary' => $summary,
            'metrics' => $metrics,
        ]];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $module
     */
    private function geographyLabel(array $payload, array $module): string
    {
        $moduleLabel = $this->text($module['geography_label'] ?? '');

        if ($moduleLabel !== '') {
            return $moduleLabel;
        }

        return match ($this->text($payload['geography_level'] ?? '')) {
            'postcode_sector' => 'Postcode sector evidence',
            'postcode_district' => 'Wider postcode district evidence',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, mixed> $guides
     * @param array<int, mixed> $articles
     * @return array<int, array{eyebrow: string, title: string, body: string, url: string, action: string, class: string, status: string, modules: array<int, string>}>
     */
    private function fallbackCardsFromChain(array $chain): array
    {
        $cards = [];

        foreach ($chain as $item) {
            if (! is_array($item)) {
                continue;
            }

            $level = $this->text($item['level'] ?? '');
            $status = $this->text($item['status'] ?? 'missing');
            $title = $this->text($item['label'] ?? '');
            $url = $this->normalisePublicUrl($this->text($item['url'] ?? ''));
            $modules = is_array($item['available_modules'] ?? null)
                ? array_values(array_filter(array_map(fn (mixed $module): string => $this->text($module), $item['available_modules'])))
                : [];

            if ($title === '') {
                $title = $this->levelLabel($level);
            }

            $cards[] = [
                'eyebrow' => $this->levelLabel($level),
                'title' => $title,
                'body' => $this->text($item['explanation'] ?? ''),
                'url' => $url,
                'action' => $this->actionLabel($level),
                'class' => 'cpi-postcode-area-fallback-card--'.sanitize_html_class($level ?: 'context').' cpi-postcode-area-fallback-card--status-'.sanitize_html_class($status),
                'status' => $status,
                'modules' => $modules,
            ];
        }

        return $cards;
    }

    /**
     * @param array<int, array{eyebrow: string, title: string, body: string, url: string, action: string, class: string, status: string, modules: array<int, string>}> $cards
     * @return array{eyebrow: string, title: string, body: string, url: string, action: string, class: string, status: string, modules: array<int, string>}|null
     */
    private function primaryGuideCard(array $cards): ?array
    {
        foreach ($cards as $card) {
            if (str_contains($card['class'], 'associated_location_guide') && $card['url'] !== '') {
                return $card;
            }
        }

        foreach ($cards as $card) {
            if ($card['url'] !== '' && $card['status'] === 'fallback_available') {
                return $card;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{eyebrow: string, title: string, body: string, url: string, action: string, class: string, status: string, modules: array<int, string>}>
     */
    private function fallbackContextCards(array $payload, array $guides, array $articles): array
    {
        $areaKey = $this->text($payload['area_key'] ?? $payload['public_label'] ?? 'this area');
        $districtKey = $this->text($payload['district_key'] ?? '');
        $guide = $this->firstLink($guides);
        $article = $this->firstLink($articles);
        $geographyLevel = $this->text($payload['geography_level'] ?? '');
        $districtTitle = $districtKey !== '' ? $districtKey.' postcode district' : 'Wider postcode district';
        $districtCard = $geographyLevel === 'postcode_district'
            ? [
                'eyebrow' => 'Sector context',
                'title' => 'Postcode sector evidence',
                'body' => 'More specific sector evidence can replace this wider district view when an approved sector export is available.',
                'url' => '',
                'action' => '',
                'class' => 'cpi-postcode-area-fallback-card--district',
                'status' => 'missing',
                'modules' => [],
            ]
            : [
                'eyebrow' => 'Wider postcode context',
                'title' => $districtTitle,
                'body' => 'When sector evidence is unavailable, the route can fall back to approved aggregate district evidence.',
                'url' => $districtKey !== '' ? home_url('/near-me/'.sanitize_title($districtKey).'/') : '',
                'action' => 'Open district view',
                'class' => 'cpi-postcode-area-fallback-card--district',
                'status' => 'missing',
                'modules' => [],
            ];

        return [
            [
                'eyebrow' => $geographyLevel === 'postcode_district' ? 'Current evidence level' : 'First evidence level',
                'title' => $geographyLevel === 'postcode_district' ? $districtTitle : $areaKey.' postcode sector',
                'body' => $geographyLevel === 'postcode_district'
                    ? 'This page is currently using approved aggregate district evidence.'
                    : 'Use approved aggregate sector evidence first when it is available.',
                'url' => '',
                'action' => '',
                'class' => 'cpi-postcode-area-fallback-card--current',
                'status' => 'active',
                'modules' => [],
            ],
            $districtCard,
            [
                'eyebrow' => 'Closest town context',
                'title' => $guide['title'] !== '' ? $guide['title'] : 'Location Intelligence guide',
                'body' => $guide['title'] !== ''
                    ? 'Use the linked Location Intelligence guide as broader local context, not as a postcode-level result.'
                    : 'No closest-town Location Intelligence guide is included in the current static export yet.',
                'url' => $guide['url'],
                'action' => 'Open source guide',
                'class' => 'cpi-postcode-area-fallback-card--location',
                'status' => $guide['url'] !== '' ? 'fallback_available' : 'missing',
                'modules' => [],
            ],
            [
                'eyebrow' => 'Broader Cornwall context',
                'title' => $article['title'] !== '' ? $article['title'] : 'Cornwall-wide evidence',
                'body' => $article['title'] !== ''
                    ? 'Use broader public articles only when direct postcode and town context is not available.'
                    : 'No Cornwall-wide fallback article is linked in the current static export yet.',
                'url' => $article['url'],
                'action' => 'Read article',
                'class' => 'cpi-postcode-area-fallback-card--cornwall',
                'status' => $article['url'] !== '' ? 'fallback_available' : 'missing',
                'modules' => [],
            ],
        ];
    }

    /**
     * @param array<int, mixed> $items
     * @return array{title: string, url: string}
     */
    private function firstLink(array $items): array
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = $this->text($item['title'] ?? $item['label'] ?? '');
            $url = $this->text($item['url'] ?? $item['href'] ?? '');
            $slug = $this->text($item['slug'] ?? '');

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

    private function levelLabel(string $level): string
    {
        return match ($level) {
            'postcode_sector' => 'Postcode sector',
            'postcode_district' => 'Postcode district',
            'associated_location_guide' => 'Associated Location guide',
            'cornwall_wide_context' => 'Cornwall-wide context',
            default => 'Fallback context',
        };
    }

    private function actionLabel(string $level): string
    {
        return match ($level) {
            'postcode_sector', 'postcode_district' => 'Open area view',
            'associated_location_guide' => 'Open guide',
            'cornwall_wide_context' => 'Open context',
            default => 'Open',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active', 'available' => 'Available here',
            'fallback_available' => 'Fallback available',
            'unavailable' => 'Unavailable',
            default => 'Not available yet',
        };
    }

    private function sourceLevelLabel(string $sourceLevel): string
    {
        return match ($sourceLevel) {
            'sector' => 'Source level: postcode sector',
            'district' => 'Source level: postcode district',
            'location_guide' => 'Source level: associated Location guide',
            'cornwall_wide' => 'Source level: Cornwall-wide context',
            default => 'Source level: none yet',
        };
    }

    private function moduleLabel(string $moduleType): string
    {
        return match ($moduleType) {
            'epc_status' => 'EPC / retrofit',
            'market' => 'Market',
            'trade_work_activity' => 'Trade / work activity',
            'planning' => 'Planning',
            'building_activity' => 'Building activity',
            default => ucwords(str_replace('_', ' ', $moduleType)),
        };
    }

    /**
     * @return array<int, string>
     */
    private function fallbackContextNotes(array $context, array $gapNotes): array
    {
        $notes = [];

        foreach (['location_context_note', 'guide_context_note', 'article_context_note'] as $key) {
            $note = $this->text($context[$key] ?? '');

            if ($note !== '') {
                $notes[] = $note;
            }
        }

        foreach ($gapNotes as $gapNote) {
            if (! is_array($gapNote)) {
                continue;
            }

            $note = $this->text($gapNote['explanation'] ?? '');

            if ($note !== '') {
                $notes[] = $note;
            }
        }

        return array_values(array_unique($notes));
    }

}
