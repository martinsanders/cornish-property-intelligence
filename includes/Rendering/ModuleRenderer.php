<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Rendering;

final class ModuleRenderer
{
    public function __construct(
        private readonly BarsRenderer $bars,
    ) {}

    /**
     * @param array<string|int, mixed> $modules
     */
    public function render(array $modules, string $classPrefix = 'cpi'): string
    {
        if ($modules === []) {
            return '';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classPrefix); ?>-modules">
            <?php foreach ($modules as $key => $module) : ?>
                <?php echo $this->card($key, $module, $classPrefix); ?>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function card(int|string $key, mixed $module, string $classPrefix): string
    {
        $title = is_array($module)
            ? $this->text($module['title'] ?? $module['label'] ?? $module['heading'] ?? $key)
            : $this->text($module);

        if ($title === '') {
            $title = (string) $key;
        }

        $moduleType = is_array($module) ? $this->text($module['module_type'] ?? $key) : (string) $key;
        $headline = is_array($module) ? $this->text($module['headline'] ?? $module['summary'] ?? '') : '';

        if ($moduleType === 'epc_status' && is_array($module)) {
            $interpretation = is_array($module['epc_interpretation'] ?? null) ? $module['epc_interpretation'] : [];
            $executiveAnswer = is_array($interpretation['executive_answer'] ?? null)
                ? $this->text($interpretation['executive_answer']['text'] ?? '')
                : '';
            $interpretationAnswer = $this->text($interpretation['answer'] ?? '');

            if ($interpretationAnswer !== '') {
                $headline = $interpretationAnswer;
            } elseif ($executiveAnswer !== '') {
                $headline = $executiveAnswer;
            }
        }

        $body = is_array($module) ? $this->text($module['body'] ?? $module['description'] ?? '') : '';
        $geography = is_array($module) ? $this->text($module['geography_label'] ?? '') : '';
        $moduleKey = sanitize_key($moduleType !== '' ? $moduleType : (string) $key);
        $moduleLabel = $this->moduleLabel($moduleType, $title);
        $moduleQuestion = $this->moduleQuestion($moduleType, $title, $geography);
        $showAnswerPrefix = $moduleType !== 'executive_answer';
        $titleText = $moduleType === 'executive_answer' ? $moduleQuestion : $moduleLabel;
        $descriptionText = $moduleType === 'executive_answer' ? '' : $moduleQuestion;
        $showHeadline = $headline !== '' && ! in_array($moduleType, ['opportunity_signals', 'published_articles'], true);

        ob_start();
        ?>
        <article class="<?php echo esc_attr($classPrefix); ?>-module <?php echo esc_attr($classPrefix); ?>-module--<?php echo esc_attr(sanitize_html_class($moduleType)); ?>" data-cpi-module-root data-cpi-module-key="<?php echo esc_attr($moduleKey); ?>">
            <header class="<?php echo esc_attr($classPrefix); ?>-module__header">
                <?php if ($moduleType === 'executive_answer' && $moduleLabel !== '') : ?>
                    <p class="<?php echo esc_attr($classPrefix); ?>-module__eyebrow"><?php echo esc_html($moduleLabel); ?></p>
                <?php endif; ?>

                <h3 class="<?php echo esc_attr($classPrefix); ?>-module__title"><?php echo esc_html($titleText); ?></h3>

                <?php if ($descriptionText !== '') : ?>
                    <p class="<?php echo esc_attr($classPrefix); ?>-module__description"><?php echo esc_html($descriptionText); ?></p>
                <?php endif; ?>

                <?php if ($showHeadline) : ?>
                    <p class="<?php echo esc_attr($classPrefix); ?>-module__headline">
                        <?php if ($showAnswerPrefix) : ?>
                            <span><?php echo esc_html__('Answer:', 'cornish-property-intelligence'); ?></span>
                        <?php endif; ?>
                        <?php echo esc_html($headline); ?>
                    </p>
                <?php endif; ?>

                <?php if ($body !== '') : ?>
                    <p><?php echo esc_html($body); ?></p>
                <?php endif; ?>

            <?php if ($geography !== '' && $classPrefix !== 'cpi-location') : ?>
                <p class="<?php echo esc_attr($classPrefix); ?>-module__meta"><?php echo esc_html($geography); ?></p>
            <?php endif; ?>
            </header>

            <?php if (is_array($module)) : ?>
                <?php if (! $this->hasStructuredEvidence($module)) : ?>
                    <?php echo $this->metrics($module, $classPrefix); ?>
                <?php endif; ?>
                <?php echo $this->executiveSignals($module, $classPrefix); ?>
                <?php echo $this->dataStudio($module, $classPrefix); ?>
                <?php if ($this->hasInteractiveCharts($module)) : ?>
                    <?php echo $this->interactiveEvidence($module, $classPrefix, $moduleType); ?>
                <?php else : ?>
                    <?php echo $this->charts($module, $classPrefix); ?>
                    <?php echo $this->legacyBarsFromModule($module, $classPrefix); ?>
                    <?php echo $this->supportingEvidence($module, $classPrefix); ?>
                <?php endif; ?>
                <?php echo $this->notes($module, $classPrefix); ?>
                <?php echo $this->links($module, $classPrefix, $moduleType); ?>
            <?php endif; ?>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $module
     */
    private function executiveSignals(array $module, string $classPrefix): string
    {
        $signals = is_array($module['executive_signals'] ?? null) ? $module['executive_signals'] : [];
        $rendered = [];

        foreach ($signals as $signal) {
            if (! is_array($signal)) {
                continue;
            }

            $label = $this->text($signal['label'] ?? '');
            $title = $this->text($signal['title'] ?? '');
            $summary = $this->text($signal['summary'] ?? '');
            $key = sanitize_html_class($this->text($signal['key'] ?? 'signal'));

            if ($title === '' && $summary === '') {
                continue;
            }

            ob_start();
            ?>
            <article class="<?php echo esc_attr($classPrefix); ?>-executive-signal <?php echo esc_attr($classPrefix); ?>-executive-signal--<?php echo esc_attr($key); ?>">
                <span class="<?php echo esc_attr($classPrefix); ?>-executive-signal__icon" aria-hidden="true"></span>
                <div>
                    <?php if ($title !== '') : ?>
                        <h4><?php echo esc_html($title); ?></h4>
                    <?php endif; ?>
                    <?php if ($summary !== '') : ?>
                        <p><?php echo esc_html($summary); ?></p>
                    <?php endif; ?>
                </div>
            </article>
            <?php

            $rendered[] = (string) ob_get_clean();
        }

        if ($rendered === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-executive-signals">'.implode('', $rendered).'</div>';
    }

    /**
     * @param array<string, mixed> $module
     */
    private function hasStructuredEvidence(array $module): bool
    {
        return is_array($module['data_studio_control'] ?? null)
            || is_array($module['interactive_charts'] ?? null)
            || is_array($module['supporting_evidence'] ?? null);
    }

    /**
     * @param array<string, mixed> $module
     */
    private function metrics(array $module, string $classPrefix): string
    {
        $metrics = is_array($module['metrics'] ?? null) ? $module['metrics'] : [];

        if ($metrics === []) {
            return '';
        }

        ob_start();
        ?>
        <dl class="<?php echo esc_attr($classPrefix); ?>-metrics">
            <?php foreach ($metrics as $label => $value) : ?>
                <?php if (is_scalar($value)) : ?>
                        <?php $dataAttribute = $this->metricDataAttribute($this->label((string) $label)); ?>
                        <div class="<?php echo esc_attr($classPrefix); ?>-metric">
                            <dt><?php echo esc_html($this->label((string) $label)); ?></dt>
                            <dd<?php echo $dataAttribute; ?>><?php echo esc_html((string) $value); ?></dd>
                        </div>
                <?php elseif (is_array($value)) : ?>
                    <?php
                    $metricLabel = $this->text($value['label'] ?? '');
                    $metricValue = $value['value'] ?? null;
                    $metricFormat = $this->text($value['format'] ?? '');
                    ?>
                    <?php if ($metricLabel !== '' && is_scalar($metricValue)) : ?>
                        <?php $dataAttribute = $this->metricDataAttribute($metricLabel); ?>
                        <div class="<?php echo esc_attr($classPrefix); ?>-metric">
                            <dt><?php echo esc_html($metricLabel); ?></dt>
                            <dd<?php echo $dataAttribute; ?>><?php echo esc_html($this->formatMetricValue($metricValue, $metricFormat)); ?></dd>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>
        <?php

        return (string) ob_get_clean();
    }

    private function metricDataAttribute(string $label): string
    {
        return match ($label) {
            'Visible trade signals' => ' data-cpi-trade-support-high-signal',
            'Leading trade signal' => ' data-cpi-trade-support-leading-trade',
            'Leading change type' => ' data-cpi-trade-support-leading-change',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $module
     */
    private function dataStudio(array $module, string $classPrefix): string
    {
        $control = is_array($module['data_studio_control'] ?? null) ? $module['data_studio_control'] : [];
        $groups = is_array($module['control_groups'] ?? null)
            ? $module['control_groups']
            : (is_array($control['groups'] ?? null) ? $control['groups'] : []);
        $groups = $this->withEpcInsightControlGroup($module, $groups);
        $title = $this->text($control['title'] ?? '');
        $summary = $this->text($module['current_view_summary'] ?? $control['summary'] ?? '');
        $coverage = $this->text($module['coverage_line'] ?? $control['coverage'] ?? '');
        $missing = is_array($control['missing'] ?? null) ? $control['missing'] : [];
        $isEpcModule = $this->text($module['module_type'] ?? '') === 'epc_status';
        $sectionClasses = [$classPrefix.'-data-studio'];

        if ($groups !== []) {
            $sectionClasses[] = $classPrefix.'-data-studio--controls';
        }

        if ($isEpcModule) {
            $sectionClasses[] = $classPrefix.'-data-studio--epc';
        }

        if ($title === '' && $summary === '' && $coverage === '' && $groups === [] && $missing === []) {
            return '';
        }

        ob_start();
        ?>
        <section class="<?php echo esc_attr(implode(' ', $sectionClasses)); ?>" aria-label="<?php echo esc_attr__('Data controls', 'cornish-property-intelligence'); ?>">
            <p class="<?php echo esc_attr($classPrefix); ?>-data-studio__eyebrow"><?php echo esc_html__('Data Studio controls', 'cornish-property-intelligence'); ?></p>

            <?php if ($title !== '') : ?>
                <h4 class="<?php echo esc_attr($classPrefix); ?>-data-studio__title"><?php echo esc_html($title); ?></h4>
            <?php endif; ?>

            <?php echo $this->controlGroups($groups, $classPrefix); ?>

            <?php if ($groups !== []) : ?>
                <?php echo $this->currentViewContext($groups, $classPrefix); ?>
            <?php endif; ?>

            <?php if ($missing !== []) : ?>
                <ul class="<?php echo esc_attr($classPrefix); ?>-data-studio__missing">
                    <?php foreach ($missing as $item) : ?>
                        <?php $message = $this->text($item); ?>
                        <?php if ($message !== '') : ?>
                            <li><?php echo esc_html($message); ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($summary !== '' || $coverage !== '' || $groups !== []) : ?>
                <div class="<?php echo esc_attr($classPrefix); ?>-data-studio__status">
                    <div>
                        <?php if ($summary !== '') : ?>
                            <p class="<?php echo esc_attr($classPrefix); ?>-data-studio__summary"><?php echo esc_html($summary); ?></p>
                        <?php endif; ?>

                        <?php if ($coverage !== '') : ?>
                            <p class="<?php echo esc_attr($classPrefix); ?>-data-studio__coverage"><?php echo esc_html($coverage); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($groups !== []) : ?>
                        <button class="<?php echo esc_attr($classPrefix); ?>-control-reset" type="button" data-cpi-reset-controls>
                            <?php echo esc_html__('Reset view', 'cornish-property-intelligence'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<int, mixed> $groups
     */
    private function currentViewContext(array $groups, string $classPrefix): string
    {
        $items = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $key = $this->text($group['key'] ?? '');
            $label = $this->text($group['label'] ?? $key);
            $value = $this->activeControlOptionLabel($group);

            if ($key === '' || $label === '' || $value === '') {
                continue;
            }

            $items[] = '<span class="'.esc_attr($classPrefix).'-current-view__item" data-cpi-current-view-item aria-label="'.esc_attr(sprintf('%s: %s', $label, $value)).'">'
                .'<span>'.esc_html($label).'</span>'
                .'<strong data-cpi-current-view-value="'.esc_attr($key).'">'.esc_html($value).'</strong>'
                .'</span>';
        }

        if ($items === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-current-view" aria-live="polite">'.implode('', $items).'</div>';
    }

    /**
     * @param array<int, mixed> $groups
     * @return array<string, mixed>|null
     */
    private function controlGroupByKey(array $groups, string $key): ?array
    {
        foreach ($groups as $group) {
            if (is_array($group) && $this->text($group['key'] ?? '') === $key) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $group
     */
    private function activeControlOptionLabel(array $group): string
    {
        $options = is_array($group['options'] ?? null) ? $group['options'] : [];
        $first = '';

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $label = $this->text($option['label'] ?? $option['value'] ?? '');

            if ($label === '') {
                continue;
            }

            $first = $first === '' ? $label : $first;

            if (($option['active'] ?? false) === true) {
                return $label;
            }
        }

        return $first;
    }

    /**
     * @param array<int, mixed> $groups
     */
    private function controlGroups(array $groups, string $classPrefix): string
    {
        $renderedGroups = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $label = $this->text($group['label'] ?? $group['key'] ?? '');
            $key = $this->text($group['key'] ?? '');
            $options = is_array($group['options'] ?? null) ? $group['options'] : [];

            if ($label === '' || $key === '' || $options === []) {
                continue;
            }
            $isInteractive = $this->isInteractiveControlGroup($key);

            ob_start();
            ?>
            <div class="<?php echo esc_attr($classPrefix); ?>-control-group" data-cpi-control-group="<?php echo esc_attr($key); ?>" data-cpi-control-mode="<?php echo esc_attr($isInteractive ? 'interactive' : 'static'); ?>">
                <p class="<?php echo esc_attr($classPrefix); ?>-control-group__label"><?php echo esc_html($label); ?></p>
                <?php echo $this->controlSelect($options, $label, $classPrefix, $isInteractive); ?>
            </div>
            <?php

            $renderedGroups[] = (string) ob_get_clean();
        }

        if ($renderedGroups === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-control-groups">'.implode('', $renderedGroups).'</div>';
    }

    /**
     * @param array<int, mixed> $options
     */
    private function controlSelect(array $options, string $label, string $classPrefix, bool $isInteractive): string
    {
        $normalised = [];

        foreach ($options as $option) {
            $optionLabel = is_array($option) ? $this->text($option['display_label'] ?? $option['label'] ?? $option['value'] ?? '') : $this->text($option);
            $optionValue = is_array($option) ? $this->text($option['value'] ?? $option['label'] ?? $option['display_label'] ?? '') : $this->text($option);
            $isActive = is_array($option) && (bool) ($option['active'] ?? false);

            if ($optionLabel === '' || $optionValue === '') {
                continue;
            }

            $normalised[] = [
                'label' => $optionLabel,
                'value' => $optionValue,
                'active' => $isActive,
            ];
        }

        if ($normalised === []) {
            return '';
        }

        if (! array_filter($normalised, fn (array $option): bool => $option['active'])) {
            $normalised[0]['active'] = true;
        }

        $active = $normalised[array_key_first(array_filter($normalised, fn (array $option): bool => $option['active']))] ?? $normalised[0];
        $readonlyClass = $isInteractive ? '' : ' '.esc_attr($classPrefix).'-select--readonly';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classPrefix); ?>-select<?php echo $readonlyClass; ?>" data-cpi-select-wrapper>
            <select class="<?php echo esc_attr($classPrefix); ?>-control-select-native" <?php echo $isInteractive ? 'data-cpi-control-select' : 'data-cpi-readonly-control'; ?> aria-label="<?php echo esc_attr($label); ?>" <?php echo $isInteractive ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>
                <?php foreach ($normalised as $option) : ?>
                    <option value="<?php echo esc_attr($option['value']); ?>" <?php selected($option['active']); ?>><?php echo esc_html($option['label']); ?></option>
                <?php endforeach; ?>
            </select>

            <button class="<?php echo esc_attr($classPrefix); ?>-select__button" type="button" data-cpi-select-button aria-haspopup="listbox" aria-expanded="false" <?php disabled(! $isInteractive); ?>>
                <span data-cpi-select-label><?php echo esc_html($active['label']); ?></span>
                <span class="<?php echo esc_attr($classPrefix); ?>-select__icon" aria-hidden="true"></span>
            </button>

            <?php if ($isInteractive) : ?>
                <div class="<?php echo esc_attr($classPrefix); ?>-select__menu" data-cpi-select-menu role="listbox" aria-label="<?php echo esc_attr($label); ?>">
                    <?php foreach ($normalised as $option) : ?>
                        <button class="<?php echo esc_attr($classPrefix); ?>-select__option" type="button" role="option" data-cpi-select-option value="<?php echo esc_attr($option['value']); ?>" aria-selected="<?php echo $option['active'] ? 'true' : 'false'; ?>">
                            <?php echo esc_html($option['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function isInteractiveControlGroup(string $key): bool
    {
        return in_array($key, [
            'period',
            'compare_with',
            'property_type',
            'metric_view',
            'source_focus',
            'trade_focus',
            'time_span',
            'epc_view',
            'epc_insight_view',
            'epc_time_range',
        ], true);
    }

    /**
     * @param array<string, mixed> $module
     * @param array<int, mixed> $groups
     * @return array<int, mixed>
     */
    private function withEpcInsightControlGroup(array $module, array $groups): array
    {
        if ($this->text($module['module_type'] ?? '') !== 'epc_status') {
            return $groups;
        }

        $groups = array_values(array_filter($groups, function (mixed $group): bool {
            if (! is_array($group)) {
                return true;
            }

            return ! in_array($this->text($group['key'] ?? ''), ['epc_trend_metric', 'epc_view'], true);
        }));

        $trendSeries = $this->epcTrendSeries($module);
        $insights = $this->epcInsights($module);
        $hasFuelTrend = $this->epcInsightTrendPayload($module, 'fuel_trends') !== null || $this->epcFuelTrendPayload($module) !== null;
        $hasPropertyTypeTrend = $this->epcInsightTrendPayload($module, 'property_type_trends') !== null;
        $hasPropertyOpportunity = $this->epcInsightRows($insights, 'poor_rating_by_property_type') !== []
            || $this->epcInsightRows($insights, 'improvement_gap_by_property_type') !== []
            || $this->epcInsightRows($insights, 'retrofit_signal_by_property_type') !== [];
        $hasFuelByPropertyType = $this->epcInsightRows($insights, 'fuel_by_property_type') !== [];

        if (count($trendSeries) < 2 && ! $hasFuelTrend && ! $hasPropertyTypeTrend && ! $hasPropertyOpportunity && ! $hasFuelByPropertyType) {
            return $groups;
        }

        $options = [
            [
                'value' => 'retrofit_opportunity',
                'label' => 'Retrofit opportunity',
                'active' => true,
            ],
            [
                'value' => 'rating_profile',
                'label' => 'Rating profile',
                'active' => false,
            ],
            [
                'value' => 'evidence_volume',
                'label' => 'Evidence volume',
                'active' => false,
            ],
        ];

        if ($hasFuelTrend) {
            $options[] = [
                'value' => 'fuel_heating',
                'label' => 'Fuel / heating trend',
                'active' => false,
            ];
        }

        if ($hasPropertyTypeTrend) {
            $options[] = [
                'value' => 'property_type_trend',
                'label' => 'Property type trend',
                'active' => false,
            ];
        }

        if ($hasPropertyOpportunity) {
            $options[] = [
                'value' => 'property_type_opportunity',
                'label' => 'Property type opportunity',
                'active' => false,
            ];
        }

        if ($hasFuelByPropertyType) {
            $options[] = [
                'value' => 'fuel_by_property_type',
                'label' => 'Fuel by property type',
                'active' => false,
            ];
        }

        array_unshift($groups, [
            'key' => 'epc_insight_view',
            'label' => 'Insight view',
            'options' => $options,
        ]);

        $hasTimelineTrend = count($trendSeries) >= 2 || $hasFuelTrend || $hasPropertyTypeTrend;

        if ($hasTimelineTrend) {
            $groups[] = [
                'key' => 'epc_time_range',
                'label' => 'Timeline',
                'options' => [
                    [
                        'value' => 'latest_period',
                        'label' => 'Latest year',
                        'active' => false,
                    ],
                    [
                        'value' => 'latest_3_years',
                        'label' => 'Latest 3 years',
                        'active' => true,
                    ],
                    [
                        'value' => 'latest_5_years',
                        'label' => 'Latest 5 years',
                        'active' => false,
                    ],
                    [
                        'value' => 'latest_10_years',
                        'label' => 'Latest 10 years',
                        'active' => false,
                    ],
                    [
                        'value' => 'all_periods',
                        'label' => 'All records',
                        'active' => false,
                    ],
                ],
            ];
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $module
     * @return array<int, array<string, mixed>>
     */
    private function epcTrendSeries(array $module): array
    {
        $charts = is_array($module['interactive_charts'] ?? null) ? $module['interactive_charts'] : [];

        foreach ($charts as $chart) {
            if (! is_array($chart) || $this->text($chart['type'] ?? '') !== 'epc-time-series') {
                continue;
            }

            $payload = is_array($chart['payload'] ?? null) ? $chart['payload'] : [];
            $series = is_array($payload['series'] ?? null) ? $payload['series'] : [];

            return array_values(array_filter($series, fn (mixed $seriesItem): bool => is_array($seriesItem)));
        }

        return [];
    }

    private function epcTrendMetricLabel(string $metric, string $fallback): string
    {
        return match ($metric) {
            'record_count' => 'EPC records',
            'poor_rating_share' => 'Poor-rating share',
            'average_current_efficiency' => 'Avg current efficiency',
            'average_potential_efficiency' => 'Avg potential efficiency',
            'average_improvement_gap' => 'Avg improvement gap',
            default => $fallback,
        };
    }

    /**
     * @param array<string, mixed> $module
     * @param array<int, mixed> $charts
     * @return array<int, mixed>
     */
    private function epcInsightCharts(array $module, array $charts): array
    {
        $insightCharts = [];

        foreach ($charts as $chart) {
            if (! is_array($chart)) {
                continue;
            }

            $type = $this->text($chart['type'] ?? '');

            if ($type === 'rating-comparison') {
                $chart['title'] = 'Current vs potential EPC rating profile';
                $chart['description'] = 'Approved aggregate EPC certificate rating buckets only.';
                $chart['insight_views'] = ['rating_profile'];
                $chart['payload']['time_slices'] = $this->epcRatingComparisonTimeSlices($module);
                $insightCharts[] = $chart;

                continue;
            }

            if ($type === 'epc-time-series') {
                $retrofitChart = $chart;
                $retrofitChart['title'] = 'Retrofit opportunity over time';
                $retrofitChart['description'] = 'Poor-rating share and improvement gap are based on EPC certificate records in each period.';
                $retrofitChart['insight_views'] = [];
                $retrofitChart['default_metric'] = 'poor_rating_share';

                $volumeChart = $chart;
                $volumeChart['title'] = 'EPC assessment records over time';
                $volumeChart['description'] = 'Certificate records lodged by period; not a count of unique homes.';
                $volumeChart['insight_views'] = ['evidence_volume'];
                $volumeChart['default_metric'] = 'record_count';
                $insightCharts[] = $volumeChart;

                continue;
            }

            $insightCharts[] = $chart;
        }

        $fuelPayload = $this->epcInsightTrendPayload($module, 'fuel_trends') ?? $this->epcFuelTrendPayload($module);

        if ($fuelPayload !== null) {
            $insightCharts[] = [
                'type' => 'source-comparison',
                'title' => 'Fuel / heating evidence trend',
                'description' => 'Main fuel categories recorded on EPC assessments by period.',
                'payload' => $fuelPayload,
                'insight_views' => ['fuel_heating'],
            ];
        }

        $propertyTrendPayload = $this->epcInsightTrendPayload($module, 'property_type_trends');

        if ($propertyTrendPayload !== null) {
            $insightCharts[] = [
                'type' => 'source-comparison',
                'title' => 'Property type evidence trend',
                'description' => 'Property type categories recorded on EPC certificate records over time.',
                'payload' => $propertyTrendPayload,
                'insight_views' => ['property_type_trend'],
            ];
        }

        $fuelByPropertyTypePayload = $this->epcFuelByPropertyTypeChartPayload($module);

        if ($fuelByPropertyTypePayload !== null) {
            $insightCharts[] = [
                'type' => 'epc-fuel-property-mix',
                'title' => 'Main fuel mix by property type',
                'description' => 'Main fuel categories recorded on EPC certificates within each property type.',
                'payload' => $fuelByPropertyTypePayload,
                'insight_views' => ['fuel_by_property_type'],
            ];
        }

        $propertyOpportunityPayload = $this->epcPropertyOpportunityChartPayload($module);

        if ($propertyOpportunityPayload !== null) {
            $propertyOpportunityPayload['time_slices'] = $this->epcOpportunityTimeSlices($module, 'property_opportunity', 'property_type');
            $insightCharts[] = [
                'type' => 'epc-opportunity-bars',
                'title' => 'Property type retrofit opportunity',
                'description' => 'Longer bars show stronger signals within EPC certificate records for each property type.',
                'payload' => $propertyOpportunityPayload,
                'insight_views' => ['retrofit_opportunity', 'property_type_opportunity'],
            ];
        }

        $fuelSignalPayload = $this->epcFuelSignalChartPayload($module);

        if ($fuelSignalPayload !== null) {
            $fuelSignalPayload['time_slices'] = $this->epcOpportunityTimeSlices($module, 'fuel_signal', 'fuel_type');
            $insightCharts[] = [
                'type' => 'epc-opportunity-bars',
                'title' => 'Fuel / heating retrofit signal',
                'description' => 'Longer bars show stronger signals by the main fuel recorded on EPC assessments.',
                'payload' => $fuelSignalPayload,
                'insight_views' => ['fuel_heating'],
            ];
        }

        return $insightCharts;
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>
     */
    private function epcInsights(array $module): array
    {
        return is_array($module['epc_insights'] ?? null) ? $module['epc_insights'] : [];
    }

    /**
     * @param array<string, mixed> $module
     * @return array<int, array<string, mixed>>
     */
    private function epcPeriodBreakdownPeriods(array $module): array
    {
        $insights = $this->epcInsights($module);
        $breakdowns = is_array($insights['period_breakdowns'] ?? null) ? $insights['period_breakdowns'] : [];
        $periods = is_array($breakdowns['periods'] ?? null) ? $breakdowns['periods'] : [];

        return array_values(array_filter($periods, fn (mixed $period): bool => is_array($period)));
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>
     */
    private function epcRatingComparisonTimeSlices(array $module): array
    {
        $periods = [];

        foreach ($this->epcPeriodBreakdownPeriods($module) as $period) {
            $comparison = is_array($period['rating_comparison'] ?? null) ? $period['rating_comparison'] : [];
            $current = is_array($comparison['current_rating_counts'] ?? null) ? $comparison['current_rating_counts'] : [];
            $potential = is_array($comparison['potential_rating_counts'] ?? null) ? $comparison['potential_rating_counts'] : [];
            $categories = array_values(array_unique(array_merge(array_keys($current), array_keys($potential))));

            sort($categories);

            if ($categories === []) {
                continue;
            }

            $periods[] = [
                'period' => $this->text($period['period'] ?? ''),
                'record_count' => is_numeric($period['record_count'] ?? null) ? (int) $period['record_count'] : null,
                'payload' => [
                    'available' => true,
                    'categories' => $categories,
                    'series' => array_values(array_filter([
                        $current === [] ? null : [
                            'name' => 'Current EPC rating',
                            'data' => array_map(fn (string $label): int => (int) ($current[$label] ?? 0), $categories),
                        ],
                        $potential === [] ? null : [
                            'name' => 'Potential EPC rating',
                            'data' => array_map(fn (string $label): int => (int) ($potential[$label] ?? 0), $categories),
                        ],
                    ])),
                ],
            ];
        }

        return $periods === [] ? [] : [
            'granularity' => 'yearly',
            'periods' => $periods,
        ];
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>|null
     */
    private function epcInsightTrendPayload(array $module, string $key): ?array
    {
        $insights = $this->epcInsights($module);
        $section = is_array($insights[$key] ?? null) ? $insights[$key] : [];
        $labels = $this->chartLabels($section['labels'] ?? []);
        $series = is_array($section['series'] ?? null) ? $section['series'] : [];
        $safeSeries = [];

        if ($labels === [] || $series === []) {
            return null;
        }

        foreach ($series as $seriesItem) {
            if (! is_array($seriesItem)) {
                continue;
            }

            $name = $this->text($seriesItem['name'] ?? $seriesItem['category'] ?? '');
            $data = is_array($seriesItem['data'] ?? null) ? array_values($seriesItem['data']) : [];

            if ($name === '' || $data === []) {
                continue;
            }

            $numericData = array_map(fn (mixed $value): int|float|null => is_numeric($value) ? $value + 0 : null, $data);

            if (! array_filter($numericData, fn (mixed $value): bool => is_numeric($value))) {
                continue;
            }

            $safeSeries[] = [
                'name' => $name,
                'type' => 'line',
                'data' => $numericData,
            ];
        }

        if ($safeSeries === []) {
            return null;
        }

        return [
            'available' => true,
            'categories' => $labels,
            'series' => $safeSeries,
            'emptyText' => 'No EPC insight trend is available for this view yet.',
        ];
    }

    /**
     * @param array<string, mixed> $insights
     * @return array<int, array<string, mixed>>
     */
    private function epcInsightRows(array $insights, string $key): array
    {
        $section = is_array($insights[$key] ?? null) ? $insights[$key] : [];
        $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];

        return array_values(array_filter($rows, fn (mixed $row): bool => is_array($row)));
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>|null
     */
    private function epcFuelByPropertyTypeChartPayload(array $module): ?array
    {
        $rows = $this->epcInsightRows($this->epcInsights($module), 'fuel_by_property_type');
        $payload = $this->epcFuelByPropertyTypeChartPayloadFromRows($rows);

        if ($payload !== null) {
            $payload['time_slices'] = $this->epcFuelByPropertyTypeTimeSlices($module);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>
     */
    private function epcFuelByPropertyTypeTimeSlices(array $module): array
    {
        $periods = [];

        foreach ($this->epcPeriodBreakdownPeriods($module) as $period) {
            $section = is_array($period['fuel_by_property_type'] ?? null) ? $period['fuel_by_property_type'] : [];
            $payload = $this->epcFuelByPropertyTypeChartPayloadFromRows(
                is_array($section['rows'] ?? null) ? $section['rows'] : []
            );

            if ($payload === null) {
                continue;
            }

            $periods[] = [
                'period' => $this->text($period['period'] ?? ''),
                'record_count' => is_numeric($period['record_count'] ?? null) ? (int) $period['record_count'] : null,
                'payload' => $payload,
            ];
        }

        return $periods === [] ? [] : [
            'granularity' => 'yearly',
            'periods' => $periods,
        ];
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<string, mixed>|null
     */
    private function epcFuelByPropertyTypeChartPayloadFromRows(array $rows): ?array
    {

        if ($rows === []) {
            return null;
        }

        $safeRows = [];
        $fuelTotals = [];

        foreach (array_slice($rows, 0, 6) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $propertyType = $this->text($row['property_type'] ?? '');
            $recordCount = is_numeric($row['record_count'] ?? null) ? (int) $row['record_count'] : null;
            $fuelMix = is_array($row['fuel_mix'] ?? null) ? $row['fuel_mix'] : [];

            if ($propertyType === '' || $fuelMix === []) {
                continue;
            }

            $mix = [];

            foreach ($fuelMix as $fuel) {
                if (! is_array($fuel)) {
                    continue;
                }

                $label = $this->text($fuel['label'] ?? '');
                $share = is_numeric($fuel['share'] ?? null) ? (float) $fuel['share'] : null;
                $count = is_numeric($fuel['count'] ?? null) ? (int) $fuel['count'] : null;

                if ($label === '' || $share === null) {
                    continue;
                }

                $mix[$label] = [
                    'share' => $share,
                    'count' => $count,
                ];
                $fuelTotals[$label] = ($fuelTotals[$label] ?? 0) + ($count ?? 0);
            }

            if ($mix === []) {
                continue;
            }

            $safeRows[] = [
                'property_type' => $propertyType,
                'record_count' => $recordCount,
                'mix' => $mix,
            ];
        }

        if ($safeRows === [] || $fuelTotals === []) {
            return null;
        }

        arsort($fuelTotals);
        $fuelLabels = array_values(array_filter(
            array_keys($fuelTotals),
            fn (string $label): bool => $label !== 'Other / limited sample',
        ));
        $fuelLabels = array_slice($fuelLabels, 0, 5);

        if (array_key_exists('Other / limited sample', $fuelTotals)) {
            $fuelLabels[] = 'Other / limited sample';
        }

        $categories = array_map(fn (array $row): string => $row['property_type'], $safeRows);
        $recordCounts = array_map(fn (array $row): ?int => $row['record_count'], $safeRows);
        $series = [];

        foreach ($fuelLabels as $fuelLabel) {
            $series[] = [
                'name' => $fuelLabel,
                'type' => 'bar',
                'stack' => 'fuel',
                'unit' => 'percent',
                'data' => array_map(function (array $row) use ($fuelLabel): array {
                    $fuel = $row['mix'][$fuelLabel] ?? null;

                    return [
                        'value' => is_array($fuel) ? (float) $fuel['share'] : 0,
                        'count' => is_array($fuel) ? $fuel['count'] : null,
                    ];
                }, $safeRows),
            ];
        }

        return [
            'available' => true,
            'categories' => $categories,
            'record_counts' => $recordCounts,
            'series' => $series,
            'emptyText' => 'No fuel by property type aggregate is available for this view yet.',
        ];
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>|null
     */
    private function epcPropertyOpportunityChartPayload(array $module): ?array
    {
        $rows = $this->epcMergedPropertyOpportunityRows($this->epcInsights($module));

        return $this->epcOpportunityChartPayloadFromRows($rows, 'property_type', 'No property type retrofit opportunity aggregate is available for this view yet.');
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<string, mixed>|null
     */
    private function epcOpportunityChartPayloadFromRows(array $rows, string $labelKey, string $emptyText): ?array
    {

        if ($rows === []) {
            return null;
        }

        $safeRows = [];

        foreach (array_slice($rows, 0, 6) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = $this->text($row[$labelKey] ?? '');

            if ($label === '') {
                continue;
            }

            $safeRows[] = [
                'label' => $label,
                'record_count' => is_numeric($row['record_count'] ?? null) ? (int) $row['record_count'] : null,
                'improvement_gap' => is_numeric($row['average_improvement_gap'] ?? null) ? (float) $row['average_improvement_gap'] : null,
                'poor_rating_share' => is_numeric($row['poor_rating_share'] ?? null) ? (float) $row['poor_rating_share'] : null,
                'retrofit_signal_share' => is_numeric($row['retrofit_signal_share'] ?? null) ? (float) $row['retrofit_signal_share'] : null,
            ];
        }

        if ($safeRows === []) {
            return null;
        }

        return [
            'available' => true,
            'categories' => array_map(fn (array $row): string => $row['label'], $safeRows),
            'record_counts' => array_map(fn (array $row): ?int => $row['record_count'], $safeRows),
            'series' => [
                [
                    'name' => 'Poor-rating share',
                    'unit' => 'percent',
                    'data' => array_map(fn (array $row): ?float => $row['poor_rating_share'], $safeRows),
                ],
                [
                    'name' => 'Retrofit signal share',
                    'unit' => 'percent',
                    'data' => array_map(fn (array $row): ?float => $row['retrofit_signal_share'], $safeRows),
                ],
                [
                    'name' => 'Improvement gap',
                    'unit' => 'score_points',
                    'data' => array_map(fn (array $row): ?float => $row['improvement_gap'], $safeRows),
                ],
            ],
            'emptyText' => $emptyText,
        ];
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>|null
     */
    private function epcFuelSignalChartPayload(array $module): ?array
    {
        $rows = $this->epcInsightRows($this->epcInsights($module), 'retrofit_signal_by_fuel_type');

        usort($rows, function (array $a, array $b): int {
            return ((int) ($b['record_count'] ?? 0)) <=> ((int) ($a['record_count'] ?? 0));
        });

        return $this->epcOpportunityChartPayloadFromRows($rows, 'fuel_type', 'No fuel or heating retrofit signal aggregate is available for this view yet.');
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>
     */
    private function epcOpportunityTimeSlices(array $module, string $sectionKey, string $labelKey): array
    {
        $periods = [];

        foreach ($this->epcPeriodBreakdownPeriods($module) as $period) {
            $section = is_array($period[$sectionKey] ?? null) ? $period[$sectionKey] : [];
            $payload = $this->epcOpportunityChartPayloadFromRows(
                is_array($section['rows'] ?? null) ? $section['rows'] : [],
                $labelKey,
                'No time-filtered EPC aggregate is available for this view yet.'
            );

            if ($payload === null) {
                continue;
            }

            $periods[] = [
                'period' => $this->text($period['period'] ?? ''),
                'record_count' => is_numeric($period['record_count'] ?? null) ? (int) $period['record_count'] : null,
                'payload' => $payload,
            ];
        }

        return $periods === [] ? [] : [
            'granularity' => 'yearly',
            'periods' => $periods,
        ];
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>|null
     */
    private function epcFuelTrendPayload(array $module): ?array
    {
        $trendPayload = $this->epcTimeSeriesPayload($module);
        $periods = is_array($trendPayload['rating_distribution_by_period'] ?? null) ? $trendPayload['rating_distribution_by_period'] : [];
        $labels = $this->chartLabels($trendPayload['labels'] ?? []);

        if ($periods === [] || $labels === []) {
            return null;
        }

        $totals = [];

        foreach ($periods as $period) {
            $fuelCounts = is_array($period['main_fuel_counts'] ?? null) ? $period['main_fuel_counts'] : [];

            foreach ($fuelCounts as $fuel => $count) {
                if (! is_numeric($count)) {
                    continue;
                }

                $fuel = $this->fuelLabel((string) $fuel);
                $totals[$fuel] = ($totals[$fuel] ?? 0) + (float) $count;
            }
        }

        if ($totals === []) {
            return null;
        }

        arsort($totals);
        $topFuelLabels = array_slice(array_values(array_filter(
            array_keys($totals),
            fn (string $label): bool => $label !== 'Other / limited sample',
        )), 0, 4);

        if (array_key_exists('Other / limited sample', $totals)) {
            $topFuelLabels[] = 'Other / limited sample';
        }

        $series = [];

        foreach ($topFuelLabels as $fuelLabel) {
            $series[] = [
                'name' => $fuelLabel,
                'type' => 'line',
                'data' => array_values(array_map(function (mixed $period) use ($fuelLabel): int|float|null {
                    $fuelCounts = is_array($period) && is_array($period['main_fuel_counts'] ?? null) ? $period['main_fuel_counts'] : [];

                    foreach ($fuelCounts as $fuel => $count) {
                        if ($this->fuelLabel((string) $fuel) === $fuelLabel && is_numeric($count)) {
                            return $count + 0;
                        }
                    }

                    return null;
                }, $periods)),
            ];
        }

        return [
            'available' => true,
            'categories' => $labels,
            'series' => $series,
            'emptyText' => 'No main-fuel EPC trend is available for this view yet.',
        ];
    }

    private function fuelLabel(string $fuel): string
    {
        $normalised = strtoupper(trim($fuel));

        if ($normalised === 'OTHER / LIMITED SAMPLE') {
            return 'Other / limited sample';
        }

        if (str_contains($normalised, 'MAINS GAS') || str_contains($normalised, 'GAS: MAINS GAS')) {
            return 'Mains gas';
        }

        if (str_contains($normalised, 'ELECTRICITY')) {
            return 'Electricity';
        }

        if (str_contains($normalised, 'OIL')) {
            return 'Oil';
        }

        if (str_contains($normalised, 'LPG')) {
            return 'LPG';
        }

        if (str_contains($normalised, 'WOOD')) {
            return 'Wood / biomass';
        }

        if (str_contains($normalised, 'COAL')) {
            return 'Coal / solid fuel';
        }

        return ucwords(strtolower(str_replace(['_', '-'], ' ', $fuel)));
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>|null
     */
    private function epcPropertyTypePayload(array $module): ?array
    {
        $distributions = is_array($module['distributions'] ?? null) ? $module['distributions'] : [];
        $propertyTypes = is_array($distributions['property_type_counts'] ?? null) ? $distributions['property_type_counts'] : [];

        if ($propertyTypes === []) {
            return null;
        }

        arsort($propertyTypes);

        $items = [];

        foreach ($propertyTypes as $label => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $items[] = [
                'label' => $this->text($label),
                'value' => $value + 0,
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            'items' => $items,
            'seriesName' => 'EPC certificate records',
        ];
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>
     */
    private function epcTimeSeriesPayload(array $module): array
    {
        $charts = is_array($module['interactive_charts'] ?? null) ? $module['interactive_charts'] : [];

        foreach ($charts as $chart) {
            if (! is_array($chart) || $this->text($chart['type'] ?? '') !== 'epc-time-series') {
                continue;
            }

            return is_array($chart['payload'] ?? null) ? $chart['payload'] : [];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $module
     */
    private function epcInsightPanelDefinitions(array $module): array
    {
        $recordCount = $this->epcMetricValue($module, ['epc_assessments', 'EPC assessments']) ?? $module['record_count'] ?? null;
        $poorCount = $this->epcMetricValue($module, ['poor_rating_count', 'Poor EPC ratings']);
        $retrofitCount = $this->epcMetricValue($module, ['retrofit_signal_count', 'Retrofit signals']);
        $currentScore = $this->epcMetricValue($module, ['average_current_efficiency', 'Average current score']);
        $potentialScore = $this->epcMetricValue($module, ['average_potential_efficiency', 'Average potential score']);
        $gap = $this->epcMetricValue($module, ['average_improvement_points', 'Average uplift']);
        $poorShare = is_numeric($poorCount) && is_numeric($recordCount) && (float) $recordCount > 0
            ? round(((float) $poorCount / (float) $recordCount) * 100, 1)
            : null;
        $retrofitShare = is_numeric($retrofitCount) && is_numeric($recordCount) && (float) $recordCount > 0
            ? round(((float) $retrofitCount / (float) $recordCount) * 100, 1)
            : null;
        $insights = $this->epcInsights($module);
        $cards = is_array($insights['insight_cards'] ?? null) ? $insights['insight_cards'] : [];
        $propertyOpportunityRows = $this->epcMergedPropertyOpportunityRows($insights);
        $fuelRows = $this->epcInsightRows($insights, 'fuel_by_property_type');
        $hasFuelTrend = $this->epcInsightTrendPayload($module, 'fuel_trends') !== null || $this->epcFuelTrendPayload($module) !== null;
        $hasPropertyTrend = $this->epcInsightTrendPayload($module, 'property_type_trends') !== null;

        $panels = [
            'retrofit_opportunity' => [
                'title' => 'How to read it',
                'summary' => 'EPC certificate records show where poor ratings, retrofit signals and improvement gaps are most visible. This is not a count of unique homes.',
                'guide' => [
                    ['label' => 'Look for', 'text' => 'property types with long red, green and gold bars. Those are the clearest retrofit-opportunity signals in the evidence.'],
                    ['label' => 'Red bars', 'text' => 'show the share of EPC certificate records with poor current ratings.'],
                    ['label' => 'Green bars', 'text' => 'show the share of records with a recorded improvement signal.'],
                    ['label' => 'Gold bars', 'text' => 'show the average current-to-potential EPC score gap.'],
                ],
                'metrics' => [
                    ['key' => 'record_count', 'label' => 'EPC assessment records', 'value' => $recordCount, 'format' => 'integer'],
                    ['key' => 'poor_rating_share', 'label' => 'Poor-rating share', 'value' => $poorShare, 'suffix' => '%'],
                    ['key' => 'retrofit_signal_share', 'label' => 'Retrofit signal share', 'value' => $retrofitShare, 'suffix' => '%'],
                    ['key' => 'average_improvement_gap', 'label' => 'Average improvement gap', 'value' => $gap, 'suffix' => ' pts'],
                ],
                'cards' => $cards,
            ],
            'rating_profile' => [
                'title' => 'How to read it',
                'summary' => 'This compares current and potential EPC rating buckets in approved aggregate certificate records.',
                'guide' => [
                    ['label' => 'Look for', 'text' => 'a shift from lower current ratings into better potential ratings.'],
                    ['label' => 'Current rating', 'text' => 'is the recorded EPC rating at assessment.'],
                    ['label' => 'Potential rating', 'text' => 'is the rating suggested by the EPC assessment after recommended improvements.'],
                ],
                'metrics' => [
                    ['key' => 'average_current_efficiency', 'label' => 'Average current score', 'value' => $currentScore],
                    ['key' => 'average_potential_efficiency', 'label' => 'Average potential score', 'value' => $potentialScore],
                    ['key' => 'average_improvement_gap', 'label' => 'Average improvement gap', 'value' => $gap, 'suffix' => ' pts'],
                ],
            ],
            'evidence_volume' => [
                'title' => 'How to read it',
                'summary' => 'This shows EPC certificate records lodged over time, not a count of unique homes or unique properties.',
                'guide' => [
                    ['label' => 'Look for', 'text' => 'periods with enough EPC certificate records to support the trend.'],
                    ['label' => 'Higher bars', 'text' => 'mean more EPC assessment records in that period.'],
                    ['label' => 'Caution', 'text' => 'record volume can reflect assessment activity, not direct changes to buildings.'],
                ],
                'metrics' => [
                    ['key' => 'record_count', 'label' => 'EPC records in view', 'value' => $recordCount, 'format' => 'integer'],
                ],
            ],
        ];

        if ($hasFuelTrend) {
            $panels['fuel_heating'] = [
                'title' => 'How to read it',
                'summary' => 'Fuel patterns are based on the main fuel recorded on EPC assessments in each period.',
                'guide' => [
                    ['label' => 'Look for', 'text' => 'fuel categories with high poor-rating or retrofit-signal bars.'],
                    ['label' => 'Fuel type', 'text' => 'means the main fuel recorded on the EPC assessment.'],
                    ['label' => 'Caution', 'text' => 'this shows certificate evidence, not fuel switching by unique homes.'],
                ],
                'metrics' => [
                    ['key' => 'record_count', 'label' => 'EPC records in view', 'value' => $recordCount, 'format' => 'integer'],
                    ['key' => 'poor_rating_share', 'label' => 'Poor-rating share', 'value' => $poorShare, 'suffix' => '%'],
                    ['key' => 'retrofit_signal_share', 'label' => 'Retrofit signal share', 'value' => $retrofitShare, 'suffix' => '%'],
                    ['key' => 'average_improvement_gap', 'label' => 'Average improvement gap', 'value' => $gap, 'suffix' => ' pts'],
                ],
            ];
        }

        if ($hasPropertyTrend) {
            $panels['property_type_trend'] = [
                'title' => 'How to read it',
                'summary' => 'Property type trends are based on EPC certificate records by period. They show evidence mix, not a direct measure of buildings changing.',
                'guide' => [
                    ['label' => 'Look for', 'text' => 'which property types make up more of the EPC records in recent periods.'],
                    ['label' => 'Evidence mix', 'text' => 'means the mix of EPC certificate records, not a count of unique properties.'],
                ],
                'metrics' => [
                    ['key' => 'record_count', 'label' => 'EPC records in view', 'value' => $recordCount, 'format' => 'integer'],
                ],
            ];
        }

        if ($propertyOpportunityRows !== []) {
            $panels['property_type_opportunity'] = [
                'title' => 'How to read it',
                'summary' => 'Among EPC assessments, these rows compare poor-rating share, retrofit signal share and average improvement gap by property type.',
                'guide' => [
                    ['label' => 'Look for', 'text' => 'the property types that repeatedly score high across the three bar panels.'],
                    ['label' => 'Strong signal', 'text' => 'means more EPC records in that category show poor ratings, upgrade potential, or larger score gaps.'],
                    ['label' => 'Caution', 'text' => 'this is certificate-record evidence, not a unique property count.'],
                ],
                'metrics' => [
                    ['key' => 'record_count', 'label' => 'EPC records in view', 'value' => $recordCount, 'format' => 'integer'],
                    ['key' => 'poor_rating_share', 'label' => 'Poor-rating share', 'value' => $poorShare, 'suffix' => '%'],
                    ['key' => 'retrofit_signal_share', 'label' => 'Retrofit signal share', 'value' => $retrofitShare, 'suffix' => '%'],
                    ['key' => 'average_improvement_gap', 'label' => 'Average improvement gap', 'value' => $gap, 'suffix' => ' pts'],
                ],
            ];
        }

        if ($fuelRows !== []) {
            $panels['fuel_by_property_type'] = [
                'title' => 'How to read it',
                'summary' => 'This view shows recorded main fuel categories within each property type among EPC certificate records.',
                'guide' => [
                    ['label' => 'Look for', 'text' => 'which fuel categories dominate each property type.'],
                    ['label' => 'Useful for', 'text' => 'spotting where heating, insulation or retrofit services may need different messaging.'],
                    ['label' => 'Caution', 'text' => 'fuel mix is based on EPC certificate records, not unique homes.'],
                ],
                'metrics' => [
                    ['key' => 'record_count', 'label' => 'EPC records in view', 'value' => $recordCount, 'format' => 'integer'],
                ],
            ];
        }

        return $panels;
    }

    /**
     * @param array<string, mixed> $module
     */
    private function epcInsightPanels(array $module, string $classPrefix): string
    {
        $rendered = [];

        foreach ($this->epcInsightPanelDefinitions($module) as $view => $panel) {
            ob_start();
            ?>
            <section class="<?php echo esc_attr($classPrefix); ?>-epc-insight-panel" data-cpi-epc-panel="<?php echo esc_attr($view); ?>"<?php echo $view === 'retrofit_opportunity' ? '' : ' hidden'; ?>>
                <div>
                    <p class="<?php echo esc_attr($classPrefix); ?>-epc-insight-panel__eyebrow"><?php echo esc_html($panel['title']); ?></p>
                    <p class="<?php echo esc_attr($classPrefix); ?>-epc-insight-panel__summary"><?php echo esc_html($panel['summary']); ?></p>
                </div>
                <?php echo $this->epcInsightGuide(is_array($panel['guide'] ?? null) ? $panel['guide'] : [], $classPrefix); ?>
            </section>
            <?php

            $rendered[] = (string) ob_get_clean();
        }

        return '<div class="'.esc_attr($classPrefix).'-epc-insight-panels">'.implode('', $rendered).'</div>';
    }

    /**
     * @param array<string, mixed> $module
     */
    private function epcInsightConclusions(array $module, string $classPrefix): string
    {
        $rendered = [];

        foreach ($this->epcInsightPanelDefinitions($module) as $view => $panel) {
            $metrics = is_array($panel['metrics'] ?? null) ? $panel['metrics'] : [];
            $cards = is_array($panel['cards'] ?? null) ? $panel['cards'] : [];
            $rows = is_array($panel['rows'] ?? null) ? $panel['rows'] : [];

            if ($metrics === [] && $cards === [] && $rows === []) {
                continue;
            }

            ob_start();
            ?>
            <section class="<?php echo esc_attr($classPrefix); ?>-epc-insight-panel <?php echo esc_attr($classPrefix); ?>-epc-insight-conclusion" data-cpi-epc-conclusion="<?php echo esc_attr($view); ?>"<?php echo $view === 'retrofit_opportunity' ? '' : ' hidden'; ?>>
                <div>
                    <p class="<?php echo esc_attr($classPrefix); ?>-epc-insight-panel__eyebrow"><?php echo esc_html__('What this shows', 'cornish-property-intelligence'); ?></p>
                    <p class="<?php echo esc_attr($classPrefix); ?>-epc-insight-panel__summary"><?php echo esc_html__('These cards summarise the selected chart filters using EPC certificate records.', 'cornish-property-intelligence'); ?></p>
                </div>
                <?php echo $this->epcInsightMetrics($metrics, $classPrefix); ?>
                <?php echo $this->epcInsightCards($cards, $classPrefix); ?>
                <?php echo $this->epcInsightRowsTable($rows, $this->text($panel['row_type'] ?? ''), $classPrefix); ?>
            </section>
            <?php

            $rendered[] = (string) ob_get_clean();
        }

        return $rendered === []
            ? ''
            : '<div class="'.esc_attr($classPrefix).'-epc-insight-conclusions">'.implode('', $rendered).'</div>';
    }

    /**
     * @param array<string, mixed> $insights
     * @return array<int, array<string, mixed>>
     */
    private function epcMergedPropertyOpportunityRows(array $insights): array
    {
        $rowsByType = [];

        foreach (['poor_rating_by_property_type', 'improvement_gap_by_property_type', 'retrofit_signal_by_property_type'] as $key) {
            foreach ($this->epcInsightRows($insights, $key) as $row) {
                $propertyType = $this->text($row['property_type'] ?? '');

                if ($propertyType === '') {
                    continue;
                }

                $rowsByType[$propertyType] ??= ['property_type' => $propertyType];
                $rowsByType[$propertyType] = array_merge($rowsByType[$propertyType], $row);
            }
        }

        return array_values($rowsByType);
    }

    /**
     * @param array<int, mixed> $cards
     */
    private function epcInsightCards(array $cards, string $classPrefix): string
    {
        $rendered = [];

        foreach ($cards as $card) {
            if (! is_array($card)) {
                continue;
            }

            $title = $this->text($card['title'] ?? '');
            $label = $this->text($card['label'] ?? '');
            $value = $card['value'] ?? null;
            $unit = $this->text($card['unit'] ?? '');
            $summary = $this->text($card['summary'] ?? '');
            $period = $this->text($card['period'] ?? '');

            if ($title === '' || $label === '') {
                continue;
            }

            ob_start();
            ?>
            <article class="<?php echo esc_attr($classPrefix); ?>-epc-insight-card">
                <p class="<?php echo esc_attr($classPrefix); ?>-epc-insight-card__title"><?php echo esc_html($title); ?></p>
                <p class="<?php echo esc_attr($classPrefix); ?>-epc-insight-card__label"><?php echo esc_html($label); ?></p>
                <?php if (is_scalar($value)) : ?>
                    <p class="<?php echo esc_attr($classPrefix); ?>-epc-insight-card__value"><?php echo esc_html($this->formatInsightValue($value, $unit)); ?></p>
                <?php endif; ?>
                <?php if ($period !== '') : ?>
                    <p class="<?php echo esc_attr($classPrefix); ?>-epc-insight-card__meta"><?php echo esc_html(sprintf(__('Latest supported period: %s', 'cornish-property-intelligence'), $period)); ?></p>
                <?php endif; ?>
                <?php if ($summary !== '') : ?>
                    <p class="<?php echo esc_attr($classPrefix); ?>-epc-insight-card__summary"><?php echo esc_html($summary); ?></p>
                <?php endif; ?>
            </article>
            <?php

            $rendered[] = (string) ob_get_clean();
        }

        if ($rendered === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-epc-insight-cards" data-cpi-epc-static-cards>'.implode('', $rendered).'</div>';
    }

    /**
     * @param array<int, mixed> $items
     */
    private function epcInsightGuide(array $items, string $classPrefix): string
    {
        $rendered = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = $this->text($item['label'] ?? '');
            $text = $this->text($item['text'] ?? '');

            if ($label === '' || $text === '') {
                continue;
            }

            $rendered[] = '<li><strong>'.esc_html($label).'</strong><span>'.esc_html($text).'</span></li>';
        }

        if ($rendered === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-epc-insight-guide" aria-label="'.esc_attr__('How to read this EPC view', 'cornish-property-intelligence').'">'
            .'<ul>'.implode('', $rendered).'</ul>'
            .'</div>';
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function epcInsightRowsTable(array $rows, string $rowType, string $classPrefix): string
    {
        if ($rows === []) {
            return '';
        }

        if ($rowType === 'fuel_by_property_type') {
            return $this->epcFuelByPropertyTypeRows($rows, $classPrefix);
        }

        if ($rowType === 'fuel_signal') {
            return $this->epcSignalBars($rows, 'fuel_type', 'Fuel / heating signal rows', $classPrefix);
        }

        if ($rowType === 'property_opportunity') {
            return $this->epcPropertyOpportunityCards($rows, $classPrefix);
        }

        return '';
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function epcSignalRows(array $rows, string $labelKey, string $title, string $classPrefix): string
    {
        $rendered = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = $this->text($row[$labelKey] ?? '');

            if ($label === '') {
                continue;
            }

            $rendered[] = '<tr>'
                .'<th scope="row">'.esc_html($label).'</th>'
                .'<td>'.esc_html($this->formatOptionalInsightValue($row['record_count'] ?? null, 'certificate_record_count')).'</td>'
                .'<td>'.esc_html($this->formatOptionalInsightValue($row['poor_rating_share'] ?? null, 'percent')).'</td>'
                .'<td>'.esc_html($this->formatOptionalInsightValue($row['retrofit_signal_share'] ?? null, 'percent')).'</td>'
                .'<td>'.esc_html($this->formatOptionalInsightValue($row['average_improvement_gap'] ?? null, 'score_points')).'</td>'
                .'</tr>';
        }

        if ($rendered === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-epc-insight-table-wrap">'
            .'<p class="'.esc_attr($classPrefix).'-epc-insight-table-title">'.esc_html($title).'</p>'
            .'<table class="'.esc_attr($classPrefix).'-epc-insight-table">'
            .'<thead><tr><th scope="col">'.esc_html__('Category', 'cornish-property-intelligence').'</th><th scope="col">'.esc_html__('Records', 'cornish-property-intelligence').'</th><th scope="col">'.esc_html__('Poor rating', 'cornish-property-intelligence').'</th><th scope="col">'.esc_html__('Retrofit signal', 'cornish-property-intelligence').'</th><th scope="col">'.esc_html__('Gap', 'cornish-property-intelligence').'</th></tr></thead>'
            .'<tbody>'.implode('', $rendered).'</tbody>'
            .'</table></div>';
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function epcPropertyOpportunityCards(array $rows, string $classPrefix): string
    {
        $safeRows = array_values(array_filter($rows, fn (mixed $row): bool => is_array($row) && $this->text($row['property_type'] ?? '') !== ''));

        if ($safeRows === []) {
            return '';
        }

        $maxGap = 0.0;
        $maxRecords = 0.0;

        foreach ($safeRows as $row) {
            $maxGap = max($maxGap, is_numeric($row['average_improvement_gap'] ?? null) ? (float) $row['average_improvement_gap'] : 0.0);
            $maxRecords = max($maxRecords, is_numeric($row['record_count'] ?? null) ? (float) $row['record_count'] : 0.0);
        }

        $rendered = [];

        foreach (array_slice($safeRows, 0, 6) as $row) {
            $propertyType = $this->text($row['property_type'] ?? '');
            $recordCount = is_numeric($row['record_count'] ?? null) ? (float) $row['record_count'] : null;
            $gap = is_numeric($row['average_improvement_gap'] ?? null) ? (float) $row['average_improvement_gap'] : null;
            $poorShare = is_numeric($row['poor_rating_share'] ?? null) ? (float) $row['poor_rating_share'] : null;
            $retrofitShare = is_numeric($row['retrofit_signal_share'] ?? null) ? (float) $row['retrofit_signal_share'] : null;
            $recordShare = $recordCount !== null && $maxRecords > 0 ? ($recordCount / $maxRecords) * 100 : null;
            $gapShare = $gap !== null && $maxGap > 0 ? ($gap / $maxGap) * 100 : null;

            ob_start();
            ?>
            <article class="<?php echo esc_attr($classPrefix); ?>-epc-opportunity-card">
                <div class="<?php echo esc_attr($classPrefix); ?>-epc-opportunity-card__header">
                    <h5><?php echo esc_html($propertyType); ?></h5>
                    <?php if ($recordCount !== null) : ?>
                        <span><?php echo esc_html(sprintf(__('%s EPC records', 'cornish-property-intelligence'), $this->formatMetricValue($recordCount, 'integer'))); ?></span>
                    <?php endif; ?>
                </div>
                <?php echo $this->epcInsightMeter(__('Improvement gap', 'cornish-property-intelligence'), $gapShare, $gap !== null ? $this->formatInsightValue($gap, 'score_points') : 'N/A', $classPrefix, 'gap'); ?>
                <?php echo $this->epcInsightMeter(__('Poor-rating share', 'cornish-property-intelligence'), $poorShare, $poorShare !== null ? $this->formatInsightValue($poorShare, 'percent') : 'N/A', $classPrefix, 'poor'); ?>
                <?php echo $this->epcInsightMeter(__('Retrofit signal share', 'cornish-property-intelligence'), $retrofitShare, $retrofitShare !== null ? $this->formatInsightValue($retrofitShare, 'percent') : 'N/A', $classPrefix, 'signal'); ?>
                <?php echo $this->epcInsightMeter(__('Evidence volume', 'cornish-property-intelligence'), $recordShare, $recordCount !== null ? $this->formatInsightValue($recordCount, 'certificate_record_count') : 'N/A', $classPrefix, 'records'); ?>
            </article>
            <?php

            $rendered[] = (string) ob_get_clean();
        }

        return '<div class="'.esc_attr($classPrefix).'-epc-opportunity-grid">'.implode('', $rendered).'</div>';
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function epcSignalBars(array $rows, string $labelKey, string $title, string $classPrefix): string
    {
        $safeRows = array_values(array_filter($rows, fn (mixed $row): bool => is_array($row) && $this->text($row[$labelKey] ?? '') !== ''));

        if ($safeRows === []) {
            return '';
        }

        $rendered = [];

        foreach (array_slice($safeRows, 0, 5) as $row) {
            $label = $this->text($row[$labelKey] ?? '');
            $recordCount = is_numeric($row['record_count'] ?? null) ? (float) $row['record_count'] : null;
            $poorShare = is_numeric($row['poor_rating_share'] ?? null) ? (float) $row['poor_rating_share'] : null;
            $retrofitShare = is_numeric($row['retrofit_signal_share'] ?? null) ? (float) $row['retrofit_signal_share'] : null;
            $gap = is_numeric($row['average_improvement_gap'] ?? null) ? (float) $row['average_improvement_gap'] : null;

            ob_start();
            ?>
            <article class="<?php echo esc_attr($classPrefix); ?>-epc-signal-row">
                <div class="<?php echo esc_attr($classPrefix); ?>-epc-signal-row__header">
                    <h5><?php echo esc_html($label); ?></h5>
                    <?php if ($recordCount !== null) : ?>
                        <span><?php echo esc_html(sprintf(__('%s EPC records', 'cornish-property-intelligence'), $this->formatMetricValue($recordCount, 'integer'))); ?></span>
                    <?php endif; ?>
                </div>
                <div class="<?php echo esc_attr($classPrefix); ?>-epc-signal-row__meters">
                    <?php echo $this->epcInsightMeter(__('Poor rating', 'cornish-property-intelligence'), $poorShare, $poorShare !== null ? $this->formatInsightValue($poorShare, 'percent') : 'N/A', $classPrefix, 'poor'); ?>
                    <?php echo $this->epcInsightMeter(__('Retrofit signal', 'cornish-property-intelligence'), $retrofitShare, $retrofitShare !== null ? $this->formatInsightValue($retrofitShare, 'percent') : 'N/A', $classPrefix, 'signal'); ?>
                    <?php if ($gap !== null) : ?>
                        <p class="<?php echo esc_attr($classPrefix); ?>-epc-signal-row__gap"><?php echo esc_html(sprintf(__('Average improvement gap: %s', 'cornish-property-intelligence'), $this->formatInsightValue($gap, 'score_points'))); ?></p>
                    <?php endif; ?>
                </div>
            </article>
            <?php

            $rendered[] = (string) ob_get_clean();
        }

        return '<div class="'.esc_attr($classPrefix).'-epc-signal-bars" aria-label="'.esc_attr($title).'">'.implode('', $rendered).'</div>';
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function epcFuelByPropertyTypeRows(array $rows, string $classPrefix): string
    {
        $rendered = [];

        foreach (array_slice($rows, 0, 5) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $propertyType = $this->text($row['property_type'] ?? '');
            $fuelMix = is_array($row['fuel_mix'] ?? null) ? $row['fuel_mix'] : [];

            if ($propertyType === '' || $fuelMix === []) {
                continue;
            }

            $segments = [];
            $legendItems = [];

            foreach (array_slice($fuelMix, 0, 5) as $index => $fuel) {
                if (! is_array($fuel)) {
                    continue;
                }

                $label = $this->text($fuel['label'] ?? '');
                $share = $fuel['share'] ?? null;
                $count = $fuel['count'] ?? null;

                if ($label === '' || ! is_numeric($share)) {
                    continue;
                }

                $shareValue = max(0.0, min(100.0, (float) $share));
                $style = '--cpi-share: '.esc_attr((string) $shareValue).'%;';
                $segmentClass = $classPrefix.'-epc-fuel-segment '.$classPrefix.'-epc-fuel-segment--'.($index + 1);
                $swatchClass = $classPrefix.'-epc-fuel-swatch '.$classPrefix.'-epc-fuel-swatch--'.($index + 1);
                $segments[] = '<span class="'.esc_attr($segmentClass).'" style="'.esc_attr($style).'" title="'.esc_attr($label.' '.$this->formatInsightValue($share, 'percent')).'"><span>'.esc_html($label).'</span></span>';
                $legendItems[] = '<li><span class="'.esc_attr($swatchClass).'"></span><span>'.esc_html($label).'</span><strong>'.esc_html($this->formatInsightValue($share, 'percent')).($count !== null && is_numeric($count) ? ' <em>('.esc_html($this->formatMetricValue($count, 'integer')).')</em>' : '').'</strong></li>';
            }

            if ($segments === []) {
                continue;
            }

            $rendered[] = '<article class="'.esc_attr($classPrefix).'-epc-fuel-row">'
                .'<div class="'.esc_attr($classPrefix).'-epc-fuel-row__header"><h5>'.esc_html($propertyType).'</h5>'
                .'<p>'.esc_html(sprintf(__('Among %s EPC certificate records', 'cornish-property-intelligence'), $this->formatMetricValue($row['record_count'] ?? '', 'integer'))).'</p></div>'
                .'<div class="'.esc_attr($classPrefix).'-epc-fuel-stack" aria-label="'.esc_attr(sprintf(__('Main fuel mix for %s', 'cornish-property-intelligence'), $propertyType)).'">'.implode('', $segments).'</div>'
                .'<ul class="'.esc_attr($classPrefix).'-epc-fuel-legend">'.implode('', $legendItems).'</ul>'
                .'</article>';
        }

        if ($rendered === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-epc-fuel-rows">'.implode('', $rendered).'</div>';
    }

    private function epcInsightMeter(string $label, mixed $share, string $value, string $classPrefix, string $tone): string
    {
        $shareValue = is_numeric($share) ? max(0.0, min(100.0, (float) $share)) : 0.0;
        $style = '--cpi-share: '.esc_attr((string) $shareValue).'%;';

        return '<div class="'.esc_attr($classPrefix).'-epc-insight-meter '.esc_attr($classPrefix).'-epc-insight-meter--'.esc_attr($tone).'">'
            .'<div class="'.esc_attr($classPrefix).'-epc-insight-meter__label"><span>'.esc_html($label).'</span><strong>'.esc_html($value).'</strong></div>'
            .'<div class="'.esc_attr($classPrefix).'-epc-insight-meter__track"><span style="'.esc_attr($style).'"></span></div>'
            .'</div>';
    }

    private function formatOptionalInsightValue(mixed $value, string $unit): string
    {
        return is_scalar($value) ? $this->formatInsightValue($value, $unit) : 'N/A';
    }

    private function formatInsightValue(mixed $value, string $unit): string
    {
        if (! is_numeric($value)) {
            return $this->text($value);
        }

        $formatted = $this->formatMetricValue($value, $unit === 'certificate_record_count' ? 'integer' : '');

        return match ($unit) {
            'percent' => $formatted.'%',
            'score_points' => $formatted.' pts',
            default => $formatted,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $metrics
     */
    private function epcInsightMetrics(array $metrics, string $classPrefix): string
    {
        $rendered = [];

        foreach ($metrics as $metric) {
            $label = $this->text($metric['label'] ?? '');
            $value = $metric['value'] ?? null;

            if ($label === '' || ! is_scalar($value)) {
                continue;
            }

            $format = $this->text($metric['format'] ?? '');
            $suffix = $this->text($metric['suffix'] ?? '');
            $key = sanitize_key($this->text($metric['key'] ?? ''));
            $attribute = $key !== ''
                ? ' data-cpi-epc-insight-metric="'.esc_attr($key).'" data-cpi-epc-insight-format="'.esc_attr($format).'" data-cpi-epc-insight-suffix="'.esc_attr($suffix).'"'
                : '';
            $rendered[] = '<div class="'.esc_attr($classPrefix).'-epc-insight-metric"'.$attribute.'><dt>'.esc_html($label).'</dt><dd>'.esc_html($this->formatMetricValue($value, $format).$suffix).'</dd></div>';
        }

        if ($rendered === []) {
            return '';
        }

        return '<dl class="'.esc_attr($classPrefix).'-epc-insight-metrics">'.implode('', $rendered).'</dl>';
    }

    /**
     * @param array<string, mixed> $module
     * @param array<int, string> $keys
     */
    private function epcMetricValue(array $module, array $keys): mixed
    {
        $metrics = is_array($module['metrics'] ?? null) ? $module['metrics'] : [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $metrics) && is_scalar($metrics[$key])) {
                return $metrics[$key];
            }
        }

        foreach ($metrics as $metric) {
            if (! is_array($metric)) {
                continue;
            }

            $label = $this->text($metric['label'] ?? '');

            if (in_array($label, $keys, true) && is_scalar($metric['value'] ?? null)) {
                return $metric['value'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $module
     */
    private function hasInteractiveCharts(array $module): bool
    {
        $charts = is_array($module['interactive_charts'] ?? null) ? $module['interactive_charts'] : [];

        foreach ($charts as $chart) {
            if (is_array($chart) && $this->interactiveChart($chart, 'cpi-probe') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $module
     */
    private function charts(array $module, string $classPrefix): string
    {
        $charts = is_array($module['charts'] ?? null) ? $module['charts'] : [];
        $renderedCharts = [];

        foreach ($charts as $chart) {
            if (! is_array($chart)) {
                continue;
            }

            $rendered = $this->chart($chart, $classPrefix);

            if ($rendered !== '') {
                $renderedCharts[] = $rendered;
            }
        }

        if ($renderedCharts === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-charts">'.implode('', $renderedCharts).'</div>';
    }

    /**
     * @param array<string, mixed> $chart
     */
    private function chart(array $chart, string $classPrefix): string
    {
        $type = $this->text($chart['type'] ?? '');

        if (! in_array($type, ['bar', 'grouped_bar'], true)) {
            return '';
        }

        $labels = $this->chartLabels($chart['labels'] ?? null);
        $series = is_array($chart['series'] ?? null) ? $chart['series'] : [];

        if ($labels === [] || $series === []) {
            return '';
        }

        $renderedSeries = [];

        foreach ($series as $seriesItem) {
            if (! is_array($seriesItem)) {
                continue;
            }

            $seriesName = $this->text($seriesItem['name'] ?? 'Series');
            $buckets = $this->seriesBuckets($labels, $seriesItem['values'] ?? null);

            if ($buckets === []) {
                continue;
            }

            $renderedSeries[] = [
                'name' => $seriesName !== '' ? $seriesName : 'Series',
                'bars' => $this->bars->render($buckets, $classPrefix),
            ];
        }

        if ($renderedSeries === []) {
            return '';
        }

        $title = $this->chartTitle($this->text($chart['title'] ?? ''));
        $description = $this->text($chart['description'] ?? '');

        ob_start();
        ?>
        <section class="<?php echo esc_attr($classPrefix); ?>-chart <?php echo esc_attr($classPrefix); ?>-chart--<?php echo esc_attr($type); ?>">
            <?php if ($title !== '') : ?>
                <h4 class="<?php echo esc_attr($classPrefix); ?>-chart__title"><?php echo esc_html($title); ?></h4>
            <?php endif; ?>

            <?php if ($description !== '') : ?>
                <p class="<?php echo esc_attr($classPrefix); ?>-chart__description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>

            <?php foreach ($renderedSeries as $rendered) : ?>
                <div class="<?php echo esc_attr($classPrefix); ?>-chart__series">
                    <?php if (count($renderedSeries) > 1) : ?>
                        <p class="<?php echo esc_attr($classPrefix); ?>-chart__series-name"><?php echo esc_html($rendered['name']); ?></p>
                    <?php endif; ?>
                    <?php echo $rendered['bars']; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @return array<int, string>
     */
    private function chartLabels(mixed $labels): array
    {
        if (! is_array($labels)) {
            return [];
        }

        $safeLabels = [];

        foreach ($labels as $label) {
            if (! is_scalar($label)) {
                continue;
            }

            $label = trim((string) $label);

            if ($label !== '') {
                $safeLabels[] = $label;
            }
        }

        return $safeLabels;
    }

    /**
     * @param array<int, string> $labels
     * @return array<string, int|float>
     */
    private function seriesBuckets(array $labels, mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $buckets = [];

        foreach ($labels as $index => $label) {
            $value = $values[$index] ?? null;

            if (! is_numeric($value)) {
                continue;
            }

            $buckets[$label] = $value + 0;
        }

        return $buckets;
    }

    /**
     * @param array<string, mixed> $module
     */
    private function interactiveCharts(array $module, string $classPrefix): string
    {
        $charts = is_array($module['interactive_charts'] ?? null) ? $module['interactive_charts'] : [];
        $renderedCharts = [];

        foreach ($charts as $chart) {
            if (! is_array($chart)) {
                continue;
            }

            $rendered = $this->interactiveChart($chart, $classPrefix);

            if ($rendered !== '') {
                $renderedCharts[] = $rendered;
            }
        }

        if ($renderedCharts === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-interactive-charts">'.implode('', $renderedCharts).'</div>';
    }

    /**
     * @param array<string, mixed> $module
     */
    private function interactiveEvidence(array $module, string $classPrefix, string $moduleType): string
    {
        $charts = is_array($module['interactive_charts'] ?? null) ? $module['interactive_charts'] : [];
        $charts = $moduleType === 'epc_status' ? $this->epcInsightCharts($module, $charts) : $charts;
        $primary = [];
        $secondary = [];

        foreach ($charts as $chart) {
            if (! is_array($chart)) {
                continue;
            }

            $rendered = $this->interactiveChart($chart, $classPrefix);

            if ($rendered === '') {
                continue;
            }

            $type = $this->text($chart['type'] ?? '');
            $insightViews = is_array($chart['insight_views'] ?? null)
                ? array_values(array_filter(array_map(fn (mixed $view): string => sanitize_html_class($this->text($view)), $chart['insight_views'])))
                : [];

            if (
                in_array($type, ['monthly-comparison', 'source-comparison', 'rating-comparison'], true)
                || ($moduleType === 'change_mix' && $type === 'distribution')
                || ($moduleType === 'epc_status' && $type === 'distribution')
                || ($moduleType === 'epc_status' && $type === 'epc-time-series')
                || ($moduleType === 'epc_status' && $type === 'epc-fuel-property-mix')
                || ($moduleType === 'epc_status' && $type === 'epc-opportunity-bars')
            ) {
                $primary[] = $rendered;
            } else {
                $secondary[] = $rendered;
            }
        }

        $supporting = $moduleType === 'epc_status' ? '' : $this->supportingEvidence($module, $classPrefix, $moduleType);
        $insightPanels = $moduleType === 'epc_status' ? $this->epcInsightPanels($module, $classPrefix) : '';
        $insightConclusions = $moduleType === 'epc_status' ? $this->epcInsightConclusions($module, $classPrefix) : '';

        if ($primary === [] && $secondary === [] && $supporting === '' && $insightPanels === '' && $insightConclusions === '') {
            return '';
        }

        $supportingIsInline = $moduleType !== 'epc_status';
        $secondaryFirst = $moduleType === 'trade_work_activity';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classPrefix); ?>-evidence-visuals">
            <?php echo $insightPanels; ?>

            <?php if ($secondaryFirst && ($secondary !== [] || ($supportingIsInline && $supporting !== ''))) : ?>
                <div class="<?php echo esc_attr($classPrefix); ?>-evidence-secondary">
                    <?php echo implode('', $secondary); ?>
                    <?php if ($supportingIsInline) : ?>
                        <?php echo $supporting; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($primary !== []) : ?>
                <div class="<?php echo esc_attr($classPrefix); ?>-evidence-primary">
                    <?php echo implode('', $primary); ?>
                </div>
            <?php endif; ?>

            <?php echo $insightConclusions; ?>

            <?php if (! $secondaryFirst && ($secondary !== [] || ($supportingIsInline && $supporting !== ''))) : ?>
                <div class="<?php echo esc_attr($classPrefix); ?>-evidence-secondary">
                    <?php echo implode('', $secondary); ?>
                    <?php if ($supportingIsInline) : ?>
                        <?php echo $supporting; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $chart
     */
    private function interactiveChart(array $chart, string $classPrefix): string
    {
        $type = $this->text($chart['type'] ?? '');
        $payload = is_array($chart['payload'] ?? null) ? $chart['payload'] : [];

        if ($type === '' || $payload === []) {
            return '';
        }

        $renderedSeries = match ($type) {
            'monthly-comparison', 'source-comparison', 'rating-comparison' => $this->seriesChartsFromPayload($payload, $classPrefix),
            'epc-time-series' => $this->epcTrendChartFromPayload($payload, $classPrefix),
            'epc-fuel-property-mix' => $this->epcFuelPropertyMixFallback($payload, $classPrefix),
            'epc-opportunity-bars' => $this->epcOpportunityBarsFallback($payload, $classPrefix),
            'distribution' => $this->distributionChartFromPayload($payload, $classPrefix),
            default => '',
        };

        if ($renderedSeries === '') {
            return '';
        }

        $title = $this->chartTitle($this->text($chart['title'] ?? ''));
        $description = $this->text($chart['description'] ?? '');
        $insights = is_array($chart['insight_views'] ?? null)
            ? array_values(array_filter(array_map(fn (mixed $view): string => sanitize_html_class($this->text($view)), $chart['insight_views'])))
            : [];
        $insightAttribute = $insights !== [] ? ' data-cpi-epc-insights="'.esc_attr(implode(' ', $insights)).'"' : '';
        $hiddenAttribute = $insights !== [] && ! in_array('retrofit_opportunity', $insights, true) ? ' hidden' : '';
        $defaultMetric = $this->text($chart['default_metric'] ?? '');
        $defaultMetricAttribute = $defaultMetric !== '' ? ' data-cpi-epc-default-metric="'.esc_attr($defaultMetric).'"' : '';
        $allPeriodsOnlyAttribute = ($chart['all_periods_only'] ?? false) === true ? ' data-cpi-epc-all-periods-only="true"' : '';

        ob_start();
        ?>
        <section class="<?php echo esc_attr($classPrefix); ?>-chart <?php echo esc_attr($classPrefix); ?>-chart--interactive <?php echo esc_attr($classPrefix); ?>-chart--<?php echo esc_attr(sanitize_html_class($type)); ?>" data-cpi-interactive-chart="<?php echo esc_attr($type); ?>"<?php echo $insightAttribute; ?><?php echo $defaultMetricAttribute; ?><?php echo $allPeriodsOnlyAttribute; ?><?php echo $hiddenAttribute; ?>>
            <?php if ($title !== '') : ?>
                <h4 class="<?php echo esc_attr($classPrefix); ?>-chart__title"><?php echo esc_html($title); ?></h4>
            <?php endif; ?>

            <?php if ($description !== '') : ?>
                <p class="<?php echo esc_attr($classPrefix); ?>-chart__description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>

            <p class="<?php echo esc_attr($classPrefix); ?>-chart__status" data-cpi-chart-status></p>

            <div data-cpi-chart-output>
                <?php echo $renderedSeries; ?>
            </div>

            <div class="<?php echo esc_attr($classPrefix); ?>-echart" data-cpi-echart aria-hidden="true"></div>

            <script type="application/json" data-cpi-chart-payload><?php echo wp_json_encode($payload); ?></script>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function seriesChartsFromPayload(array $payload, string $classPrefix): string
    {
        $labels = $this->chartLabels($payload['categories'] ?? $payload['labels'] ?? []);
        $series = is_array($payload['series'] ?? null) ? $payload['series'] : [];
        $rendered = [];

        if ($labels === [] || $series === []) {
            return '';
        }

        foreach ($series as $seriesItem) {
            if (! is_array($seriesItem)) {
                continue;
            }

            $name = $this->text($seriesItem['name'] ?? 'Series');
            $buckets = $this->seriesBuckets($labels, $seriesItem['data'] ?? $seriesItem['values'] ?? null);

            if ($buckets === []) {
                continue;
            }

            ob_start();
            ?>
            <div class="<?php echo esc_attr($classPrefix); ?>-chart__series">
                <p class="<?php echo esc_attr($classPrefix); ?>-chart__series-name"><?php echo esc_html($name !== '' ? $name : 'Series'); ?></p>
                <?php echo $this->bars->render($buckets, $classPrefix); ?>
            </div>
            <?php

            $rendered[] = (string) ob_get_clean();
        }

        return implode('', $rendered);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function epcTrendChartFromPayload(array $payload, string $classPrefix): string
    {
        $labels = $this->chartLabels($payload['labels'] ?? []);
        $series = is_array($payload['series'] ?? null) ? $payload['series'] : [];
        $recordSeries = $this->firstSeriesByMetric($series, 'record_count') ?? $series[0] ?? null;

        if ($labels === [] || ! is_array($recordSeries)) {
            return '';
        }

        $name = $this->text($recordSeries['name'] ?? 'EPC certificate records');
        $buckets = $this->seriesBuckets($labels, $recordSeries['data'] ?? $recordSeries['values'] ?? null);

        if ($buckets === []) {
            return '';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classPrefix); ?>-chart__series">
            <p class="<?php echo esc_attr($classPrefix); ?>-chart__series-name"><?php echo esc_html($name !== '' ? $name : 'EPC certificate records'); ?></p>
            <?php echo $this->bars->render($buckets, $classPrefix); ?>
        </div>
        <p class="<?php echo esc_attr($classPrefix); ?>-chart__description">
            <?php echo esc_html__('EPC certificate records by period. These are assessment records over time, not unique property counts.', 'cornish-property-intelligence'); ?>
        </p>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function epcFuelPropertyMixFallback(array $payload, string $classPrefix): string
    {
        $categories = $this->chartLabels($payload['categories'] ?? []);
        $series = is_array($payload['series'] ?? null) ? $payload['series'] : [];

        if ($categories === [] || $series === []) {
            return '';
        }

        $rows = [];

        foreach ($categories as $index => $category) {
            $segments = [];

            foreach ($series as $seriesIndex => $seriesItem) {
                if (! is_array($seriesItem)) {
                    continue;
                }

                $label = $this->text($seriesItem['name'] ?? '');
                $data = is_array($seriesItem['data'] ?? null) ? $seriesItem['data'] : [];
                $point = is_array($data[$index] ?? null) ? $data[$index] : [];
                $share = is_numeric($point['value'] ?? null) ? (float) $point['value'] : 0.0;

                if ($label === '' || $share <= 0) {
                    continue;
                }

                $class = $classPrefix.'-epc-fuel-segment '.$classPrefix.'-epc-fuel-segment--'.(($seriesIndex % 5) + 1);
                $segments[] = '<span class="'.esc_attr($class).'" style="'.esc_attr('--cpi-share: '.min(100, max(0, $share)).'%;').'" title="'.esc_attr($label.' '.$this->formatInsightValue($share, 'percent')).'"><span>'.esc_html($label).'</span></span>';
            }

            if ($segments === []) {
                continue;
            }

            $rows[] = '<div class="'.esc_attr($classPrefix).'-epc-fuel-chart-fallback-row">'
                .'<span>'.esc_html($category).'</span>'
                .'<div class="'.esc_attr($classPrefix).'-epc-fuel-stack">'.implode('', $segments).'</div>'
                .'</div>';
        }

        if ($rows === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-epc-fuel-chart-fallback">'.implode('', $rows).'</div>';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function epcOpportunityBarsFallback(array $payload, string $classPrefix): string
    {
        $categories = $this->chartLabels($payload['categories'] ?? []);
        $series = is_array($payload['series'] ?? null) ? $payload['series'] : [];

        if ($categories === [] || $series === []) {
            return '';
        }

        $rows = [];

        foreach ($categories as $index => $category) {
            $items = [];

            foreach ($series as $seriesItem) {
                if (! is_array($seriesItem)) {
                    continue;
                }

                $label = $this->text($seriesItem['name'] ?? '');
                $unit = $this->text($seriesItem['unit'] ?? '');
                $data = is_array($seriesItem['data'] ?? null) ? $seriesItem['data'] : [];
                $value = is_numeric($data[$index] ?? null) ? (float) $data[$index] : null;

                if ($label === '' || $value === null) {
                    continue;
                }

                $items[] = '<li><span>'.esc_html($label).'</span><strong>'.esc_html($this->formatInsightValue($value, $unit)).'</strong></li>';
            }

            if ($items === []) {
                continue;
            }

            $rows[] = '<div class="'.esc_attr($classPrefix).'-epc-opportunity-chart-fallback-row">'
                .'<h5>'.esc_html($category).'</h5><ul>'.implode('', $items).'</ul></div>';
        }

        if ($rows === []) {
            return '';
        }

        return '<div class="'.esc_attr($classPrefix).'-epc-opportunity-chart-fallback">'.implode('', $rows).'</div>';
    }

    /**
     * @param array<int, mixed> $series
     * @return array<string, mixed>|null
     */
    private function firstSeriesByMetric(array $series, string $metric): ?array
    {
        foreach ($series as $seriesItem) {
            if (is_array($seriesItem) && $this->text($seriesItem['metric'] ?? '') === $metric) {
                return $seriesItem;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function distributionChartFromPayload(array $payload, string $classPrefix): string
    {
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $buckets = $this->itemsToBuckets($items);

        if ($buckets === []) {
            return '';
        }

        return $this->bars->render($buckets, $classPrefix);
    }

    /**
     * @param array<int, mixed> $items
     * @return array<string, int|float>
     */
    private function itemsToBuckets(array $items): array
    {
        $buckets = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = $this->text($item['label'] ?? $item['name'] ?? $item['code'] ?? $index);
            $value = $item['value'] ?? null;

            if ($label !== '' && is_numeric($value)) {
                $buckets[$label] = $value + 0;
            }
        }

        return $buckets;
    }

    /**
     * @param array<string, mixed> $module
     */
    private function supportingEvidence(array $module, string $classPrefix, string $moduleType = ''): string
    {
        $items = is_array($module['supporting_evidence'] ?? null) ? $module['supporting_evidence'] : [];
        $renderedItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $rendered = $this->supportingEvidenceCard($item, $classPrefix, $moduleType);

            if ($rendered !== '') {
                $renderedItems[] = $rendered;
            }
        }

        if ($renderedItems === []) {
            return '';
        }

        $evidenceClass = $classPrefix.'-supporting-evidence '.$classPrefix.'-supporting-evidence--'.sanitize_html_class($moduleType);
        $eyebrow = $moduleType === 'epc_status' ? 'All-record EPC summary' : 'Supporting evidence';
        $attributes = $moduleType === 'epc_status' ? ' data-cpi-epc-all-record-summary' : '';

        return '<div class="'.esc_attr($evidenceClass).'"'.$attributes.'>'
            .'<p class="'.esc_attr($classPrefix).'-supporting-evidence__eyebrow">'.esc_html__($eyebrow, 'cornish-property-intelligence').'</p>'
            .'<div class="'.esc_attr($classPrefix).'-supporting-evidence__grid">'.implode('', $renderedItems).'</div>'
            .'</div>';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function supportingEvidenceCard(array $item, string $classPrefix, string $moduleType = ''): string
    {
        $label = $this->text($item['label'] ?? $item['title'] ?? '');
        $summary = $this->text($item['summary'] ?? '');
        $metrics = is_array($item['metrics'] ?? null) ? $item['metrics'] : [];
        $sourceTotals = is_array($item['source_totals'] ?? null) ? $item['source_totals'] : [];
        $key = $this->text($item['key'] ?? '');

        if ($label === '' && $summary === '' && $metrics === [] && $sourceTotals === []) {
            return '';
        }

        if (strcasecmp($label, 'Supporting evidence') === 0) {
            $label = '';
        }

        if ($summary === '' && $key === 'current_period') {
            $summary = __('Selected period', 'cornish-property-intelligence');
        }

        $cardClass = $classPrefix.'-supporting-card';

        if ($key !== '') {
            $cardClass .= ' '.$classPrefix.'-supporting-card--'.sanitize_html_class($key);
        }

        if ($moduleType !== '') {
            $cardClass .= ' '.$classPrefix.'-supporting-card--module-'.sanitize_html_class($moduleType);
        }

        ob_start();
        ?>
        <section class="<?php echo esc_attr($cardClass); ?>">
            <?php if ($label !== '') : ?>
                <h4 class="<?php echo esc_attr($classPrefix); ?>-supporting-card__title"><?php echo esc_html($label); ?></h4>
            <?php endif; ?>

            <?php if ($summary !== '') : ?>
                <p class="<?php echo esc_attr($classPrefix); ?>-supporting-card__summary"><?php echo esc_html($summary); ?></p>
            <?php endif; ?>

            <?php echo $this->metrics(['metrics' => $metrics], $classPrefix); ?>
            <?php echo $this->sourceTotals($sourceTotals, $classPrefix); ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<int, mixed> $sourceTotals
     */
    private function sourceTotals(array $sourceTotals, string $classPrefix): string
    {
        if ($sourceTotals === []) {
            return '';
        }

        ob_start();
        ?>
        <dl class="<?php echo esc_attr($classPrefix); ?>-source-totals">
            <?php foreach ($sourceTotals as $source) : ?>
                <?php
                $label = is_array($source) ? $this->text($source['label'] ?? '') : '';
                $description = is_array($source) ? $this->text($source['description'] ?? '') : '';
                $value = is_array($source) ? ($source['value'] ?? null) : null;
                ?>
                <?php if ($label !== '' && is_scalar($value)) : ?>
                    <div class="<?php echo esc_attr($classPrefix); ?>-source-total">
                        <dt><?php echo esc_html($label); ?></dt>
                        <dd data-cpi-trade-support-source-count="<?php echo esc_attr($label); ?>"><?php echo esc_html($this->formatMetricValue($value, 'integer')); ?></dd>
                        <?php if ($description !== '') : ?>
                            <p><?php echo esc_html($description); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $module
     */
    private function legacyBarsFromModule(array $module, string $classPrefix): string
    {
        $buckets = [];

        if (is_array($module['charts']['buckets'] ?? null)) {
            $buckets = $module['charts']['buckets'];
        } elseif (is_array($module['chart']['buckets'] ?? null)) {
            $buckets = $module['chart']['buckets'];
        } elseif (is_array($module['buckets'] ?? null)) {
            $buckets = $module['buckets'];
        } elseif (is_array($module['distributions'] ?? null)) {
            $distributions = $module['distributions'];
            $firstDistribution = reset($distributions);
            $buckets = is_array($firstDistribution) ? $firstDistribution : [];
        }

        return $buckets !== [] ? $this->bars->render($buckets, $classPrefix) : '';
    }

    /**
     * @param array<string, mixed> $module
     */
    private function notes(array $module, string $classPrefix): string
    {
        if ($classPrefix === 'cpi-location') {
            return '';
        }

        $notes = array_values(array_filter([
            $this->text($module['coverage_note'] ?? ''),
            $this->text($module['privacy_note'] ?? ''),
        ], fn (string $note): bool => $note !== ''));

        if ($notes === []) {
            return '';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classPrefix); ?>-module__notes">
            <?php foreach ($notes as $note) : ?>
                <p><?php echo esc_html($note); ?></p>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $module
     */
    private function links(array $module, string $classPrefix, string $moduleType = ''): string
    {
        $links = is_array($module['articles'] ?? null)
            ? $module['articles']
            : (is_array($module['links'] ?? null) ? $module['links'] : []);

        if ($links === []) {
            return '';
        }

        if ($moduleType === 'published_articles') {
            ob_start();
            ?>
            <div class="<?php echo esc_attr($classPrefix); ?>-article-cards">
                <?php foreach ($links as $link) : ?>
                    <?php
                    $label = is_array($link) ? $this->text($link['title'] ?? $link['label'] ?? '') : $this->text($link);
                    $excerpt = is_array($link) ? $this->text($link['excerpt'] ?? '') : '';
                    $topic = is_array($link) ? $this->text($link['topic'] ?? '') : '';
                    $slug = is_array($link) ? $this->text($link['slug'] ?? '') : '';
                    $url = is_array($link) ? $this->text($link['url'] ?? $link['href'] ?? '') : '';

                    if ($url === '' && $slug !== '') {
                        $url = home_url('/articles/'.$slug.'/');
                    }
                    ?>
                    <?php if ($label !== '') : ?>
                        <article class="<?php echo esc_attr($classPrefix); ?>-article-card">
                            <div class="<?php echo esc_attr($classPrefix); ?>-article-card__media" aria-hidden="true"></div>
                            <?php if ($topic !== '') : ?>
                                <p class="<?php echo esc_attr($classPrefix); ?>-article-card__topic"><?php echo esc_html($topic); ?></p>
                            <?php endif; ?>
                            <h4>
                                <?php if ($url !== '') : ?>
                                    <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($label); ?>
                                <?php endif; ?>
                            </h4>
                            <?php if ($excerpt !== '') : ?>
                                <p><?php echo esc_html($excerpt); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        ob_start();
        ?>
        <ul class="<?php echo esc_attr($classPrefix); ?>-links">
            <?php foreach ($links as $link) : ?>
                <?php
                $label = is_array($link) ? $this->text($link['title'] ?? $link['label'] ?? '') : $this->text($link);
                $url = is_array($link) ? $this->text($link['url'] ?? $link['href'] ?? '') : '';
                ?>
                <?php if ($label !== '') : ?>
                    <li>
                        <?php if ($url !== '') : ?>
                            <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($label); ?>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        <?php

        return (string) ob_get_clean();
    }

    private function label(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    private function moduleLabel(string $moduleType, string $fallback): string
    {
        return match ($moduleType) {
            'market' => 'Market',
            'trade_work_activity' => 'Trade / work activity',
            'epc_status' => 'EPC / retrofit',
            'change_mix' => 'Change mix',
            'opportunity_signals' => 'Opportunity signals',
            'published_articles' => 'Published articles',
            'executive_answer' => 'Executive answer',
            default => $this->label($fallback !== '' ? $fallback : $moduleType),
        };
    }

    private function moduleQuestion(string $moduleType, string $fallback, string $geography = ''): string
    {
        $locationSuffix = $geography !== '' ? ' in '.$geography : '';

        return match ($moduleType) {
            'market' => 'How active is the property market'.$locationSuffix.', and what kind of homes are moving?',
            'trade_work_activity' => 'Is real property work activity rising'.$locationSuffix.', and what kind of work is driving it?',
            'epc_status' => 'How much retrofit potential exists here?',
            'change_mix' => 'What type of property change is happening?',
            'opportunity_signals' => 'A concise read of the strongest aggregate signals and the audiences most likely to care.',
            'published_articles' => 'Further reading connections from and around the wider Cornwall market signals behind this view.',
            'executive_answer' => 'What the evidence suggests',
            default => $this->label($fallback !== '' ? $fallback : $moduleType),
        };
    }

    private function chartTitle(string $title): string
    {
        return match ($title) {
            'Change type mix' => 'Change type distribution',
            default => $title,
        };
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function formatMetricValue(mixed $value, string $format): string
    {
        if ($format === 'currency_gbp' && is_numeric($value)) {
            return '£'.number_format_i18n((float) $value, 0);
        }

        if ($format === 'integer' && is_numeric($value)) {
            return number_format_i18n((int) $value);
        }

        if (is_numeric($value)) {
            return number_format_i18n((float) $value, floor((float) $value) === (float) $value ? 0 : 1);
        }

        return (string) $value;
    }
}
