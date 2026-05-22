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

        return $this->modules->render($modules, 'cpi-location')
            .$this->missingEvidenceFamilies($modules);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function guides(?array $payload): string
    {
        if ($payload === null) {
            return '';
        }

        $guides = is_array($payload['associated_guides'] ?? null)
            ? $payload['associated_guides']
            : (is_array($payload['guides'] ?? null) ? $payload['guides'] : []);

        $articles = is_array($payload['associated_articles'] ?? null) ? $payload['associated_articles'] : [];
        $fallbackCards = $this->fallbackContextCards($payload, $guides, $articles);

        ob_start();
        ?>
        <section class="cpi-postcode-area-fallback-context" aria-label="<?php echo esc_attr__('Fallback evidence context', 'cornish-property-intelligence'); ?>">
            <div class="cpi-section-heading">
                <p class="cpi-virtual-page__eyebrow"><?php echo esc_html__('Fallback evidence context', 'cornish-property-intelligence'); ?></p>
                <h2><?php echo esc_html__('How this area view fills evidence gaps', 'cornish-property-intelligence'); ?></h2>
                <p><?php echo esc_html__('Near Me pages should prefer the most local approved aggregate evidence, then widen the context only when that evidence is not available.', 'cornish-property-intelligence'); ?></p>
            </div>

            <div class="cpi-postcode-area-fallback-context__grid">
                <?php foreach ($fallbackCards as $card) : ?>
                    <article class="cpi-postcode-area-fallback-card <?php echo esc_attr($card['class']); ?>">
                        <p class="cpi-postcode-area-fallback-card__eyebrow"><?php echo esc_html($card['eyebrow']); ?></p>
                        <h3><?php echo esc_html($card['title']); ?></h3>
                        <p><?php echo esc_html($card['body']); ?></p>
                        <?php if ($card['url'] !== '') : ?>
                            <a class="cpi-button cpi-button--secondary wp-element-button" href="<?php echo esc_url($card['url']); ?>"><?php echo esc_html($card['action']); ?></a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
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
                is_array($module['charts'] ?? null) ? $module['charts'] : []
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

            if ($this->text($module['current_view_summary'] ?? '') === '') {
                $module['current_view_summary'] = 'Current view: approved aggregate evidence';
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
     * @param array<int, mixed> $charts
     * @return array<int, array<string, mixed>>
     */
    private function interactiveChartsFromStaticCharts(array $charts): array
    {
        $interactiveCharts = [];

        foreach ($charts as $chart) {
            if (! is_array($chart)) {
                continue;
            }

            $interactiveChart = $this->distributionFromChart($chart);

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
    private function distributionFromChart(array $chart): ?array
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

        return [
            'type' => 'distribution',
            'title' => $this->text($chart['title'] ?? ''),
            'description' => $this->text($chart['description'] ?? ''),
            'payload' => [
                'seriesName' => $this->text($firstSeries['name'] ?? 'Aggregate records'),
                'items' => $items,
            ],
        ];
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
            'key' => 'current_period',
            'label' => 'Evidence summary',
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
     * @param array<int|string, mixed> $modules
     */
    private function missingEvidenceFamilies(array $modules): string
    {
        $presentTypes = [];

        foreach ($modules as $module) {
            if (is_array($module)) {
                $presentTypes[] = $this->text($module['module_type'] ?? '');
            }
        }

        $cards = [];

        foreach ([
            'market' => ['Market evidence', 'Approved aggregate market evidence is not available for this postcode geography yet.'],
            'trade_work_activity' => ['Trade / work activity', 'Approved aggregate work activity evidence is not available for this postcode geography yet.'],
            'planning_building' => ['Planning and building activity', 'Planning, Building Control and Competent Person summaries will appear when approved postcode evidence is exported.'],
        ] as $type => [$title, $message]) {
            if (in_array($type, $presentTypes, true)) {
                continue;
            }

            $cards[] = [
                'title' => $title,
                'message' => $message,
            ];
        }

        if ($cards === []) {
            return '';
        }

        ob_start();
        ?>
        <section class="cpi-postcode-area-evidence-families" aria-label="<?php echo esc_attr__('Evidence availability', 'cornish-property-intelligence'); ?>">
            <p class="cpi-postcode-area-evidence-families__eyebrow"><?php echo esc_html__('Evidence availability', 'cornish-property-intelligence'); ?></p>
            <div class="cpi-postcode-area-evidence-families__grid">
                <?php foreach ($cards as $card) : ?>
                    <article class="cpi-postcode-area-evidence-family">
                        <h3><?php echo esc_html($card['title']); ?></h3>
                        <p><?php echo esc_html($card['message']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, mixed> $guides
     * @param array<int, mixed> $articles
     * @return array<int, array{eyebrow: string, title: string, body: string, url: string, action: string, class: string}>
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
            ]
            : [
                'eyebrow' => 'Wider postcode context',
                'title' => $districtTitle,
                'body' => 'When sector evidence is unavailable, the route can fall back to approved aggregate district evidence.',
                'url' => $districtKey !== '' ? home_url('/near-me/'.sanitize_title($districtKey).'/') : '',
                'action' => 'Open district view',
                'class' => 'cpi-postcode-area-fallback-card--district',
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
                    'url' => $url,
                ];
            }
        }

        return [
            'title' => '',
            'url' => '',
        ];
    }
}
