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

        $headline = is_array($module) ? $this->text($module['headline'] ?? $module['summary'] ?? '') : '';
        $body = is_array($module) ? $this->text($module['body'] ?? $module['description'] ?? '') : '';
        $geography = is_array($module) ? $this->text($module['geography_label'] ?? '') : '';
        $moduleType = is_array($module) ? $this->text($module['module_type'] ?? $key) : (string) $key;
        $moduleLabel = $this->moduleLabel($moduleType, $title);
        $moduleQuestion = $this->moduleQuestion($moduleType, $title, $geography);
        $showAnswerPrefix = $moduleType !== 'executive_answer';
        $titleText = $moduleType === 'executive_answer' ? $moduleQuestion : $moduleLabel;
        $descriptionText = $moduleType === 'executive_answer' ? '' : $moduleQuestion;
        $showHeadline = $headline !== '' && ! in_array($moduleType, ['opportunity_signals', 'published_articles'], true);

        ob_start();
        ?>
        <article class="<?php echo esc_attr($classPrefix); ?>-module <?php echo esc_attr($classPrefix); ?>-module--<?php echo esc_attr(sanitize_html_class($moduleType)); ?>" data-cpi-module-root>
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
        $title = $this->text($control['title'] ?? '');
        $summary = $this->text($module['current_view_summary'] ?? $control['summary'] ?? '');
        $coverage = $this->text($module['coverage_line'] ?? $control['coverage'] ?? '');
        $missing = is_array($control['missing'] ?? null) ? $control['missing'] : [];

        if ($title === '' && $summary === '' && $coverage === '' && $groups === [] && $missing === []) {
            return '';
        }

        ob_start();
        ?>
        <section class="<?php echo esc_attr($classPrefix); ?>-data-studio" aria-label="<?php echo esc_attr__('Data controls', 'cornish-property-intelligence'); ?>">
            <p class="<?php echo esc_attr($classPrefix); ?>-data-studio__eyebrow"><?php echo esc_html__('Data Studio controls', 'cornish-property-intelligence'); ?></p>

            <?php if ($title !== '') : ?>
                <h4 class="<?php echo esc_attr($classPrefix); ?>-data-studio__title"><?php echo esc_html($title); ?></h4>
            <?php endif; ?>

            <?php echo $this->controlGroups($groups, $classPrefix); ?>

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
        ], true);
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

            if (
                in_array($type, ['monthly-comparison', 'source-comparison', 'rating-comparison'], true)
                || ($moduleType === 'change_mix' && $type === 'distribution')
                || ($moduleType === 'epc_status' && $type === 'distribution')
            ) {
                $primary[] = $rendered;
            } else {
                $secondary[] = $rendered;
            }
        }

        $supporting = $this->supportingEvidence($module, $classPrefix, $moduleType);

        if ($primary === [] && $secondary === [] && $supporting === '') {
            return '';
        }

        $supportingIsInline = $moduleType !== 'epc_status';
        $secondaryFirst = $moduleType === 'trade_work_activity';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classPrefix); ?>-evidence-visuals">
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

            <?php if ($moduleType === 'epc_status' && $supporting !== '') : ?>
                <?php echo $supporting; ?>
            <?php endif; ?>

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
            'distribution' => $this->distributionChartFromPayload($payload, $classPrefix),
            default => '',
        };

        if ($renderedSeries === '') {
            return '';
        }

        $title = $this->chartTitle($this->text($chart['title'] ?? ''));
        $description = $this->text($chart['description'] ?? '');

        ob_start();
        ?>
        <section class="<?php echo esc_attr($classPrefix); ?>-chart <?php echo esc_attr($classPrefix); ?>-chart--interactive <?php echo esc_attr($classPrefix); ?>-chart--<?php echo esc_attr(sanitize_html_class($type)); ?>" data-cpi-interactive-chart="<?php echo esc_attr($type); ?>">
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
        $eyebrow = $moduleType === 'epc_status' ? 'EPC summary' : 'Supporting evidence';

        return '<div class="'.esc_attr($evidenceClass).'">'
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
