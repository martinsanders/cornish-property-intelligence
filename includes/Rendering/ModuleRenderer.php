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

        ob_start();
        ?>
        <article class="<?php echo esc_attr($classPrefix); ?>-module">
            <h3 class="<?php echo esc_attr($classPrefix); ?>-module__title"><?php echo esc_html($this->label($title)); ?></h3>

            <?php if ($headline !== '') : ?>
                <p class="<?php echo esc_attr($classPrefix); ?>-module__headline"><?php echo esc_html($headline); ?></p>
            <?php endif; ?>

            <?php if ($body !== '') : ?>
                <p><?php echo esc_html($body); ?></p>
            <?php endif; ?>

            <?php if ($geography !== '') : ?>
                <p class="<?php echo esc_attr($classPrefix); ?>-module__meta"><?php echo esc_html($geography); ?></p>
            <?php endif; ?>

            <?php if (is_array($module)) : ?>
                <?php echo $this->metrics($module, $classPrefix); ?>
                <?php echo $this->charts($module, $classPrefix); ?>
                <?php echo $this->legacyBarsFromModule($module, $classPrefix); ?>
                <?php echo $this->notes($module, $classPrefix); ?>
                <?php echo $this->links($module, $classPrefix); ?>
            <?php endif; ?>
        </article>
        <?php

        return (string) ob_get_clean();
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
                    <div class="<?php echo esc_attr($classPrefix); ?>-metric">
                        <dt><?php echo esc_html($this->label((string) $label)); ?></dt>
                        <dd><?php echo esc_html((string) $value); ?></dd>
                    </div>
                <?php elseif (is_array($value)) : ?>
                    <?php
                    $metricLabel = $this->text($value['label'] ?? '');
                    $metricValue = $value['value'] ?? null;
                    $metricFormat = $this->text($value['format'] ?? '');
                    ?>
                    <?php if ($metricLabel !== '' && is_scalar($metricValue)) : ?>
                        <div class="<?php echo esc_attr($classPrefix); ?>-metric">
                            <dt><?php echo esc_html($metricLabel); ?></dt>
                            <dd><?php echo esc_html($this->formatMetricValue($metricValue, $metricFormat)); ?></dd>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>
        <?php

        return (string) ob_get_clean();
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

        $title = $this->text($chart['title'] ?? '');
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
    private function links(array $module, string $classPrefix): string
    {
        $links = is_array($module['articles'] ?? null)
            ? $module['articles']
            : (is_array($module['links'] ?? null) ? $module['links'] : []);

        if ($links === []) {
            return '';
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
