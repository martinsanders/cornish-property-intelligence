<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Rendering;

final class BarsRenderer
{
    /**
     * @param array<string|int, mixed> $buckets
     */
    public function render(array $buckets, string $classPrefix = 'cpi'): string
    {
        $safeBuckets = [];

        foreach ($buckets as $label => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $safeBuckets[(string) $label] = (float) $value;
        }

        if ($safeBuckets === []) {
            return '';
        }

        $max = max($safeBuckets);

        if ($max <= 0) {
            return '';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classPrefix); ?>-bars" role="list">
            <?php foreach ($safeBuckets as $label => $value) : ?>
                <?php $width = $value > 0 ? max(4, min(100, (int) round(($value / $max) * 100))) : 0; ?>
                <div class="<?php echo esc_attr($classPrefix); ?>-bar" role="listitem">
                    <div class="<?php echo esc_attr($classPrefix); ?>-bar__label-row">
                        <span><?php echo esc_html($this->label($label)); ?></span>
                        <span><?php echo esc_html($this->formatNumber($value)); ?></span>
                    </div>
                    <div class="<?php echo esc_attr($classPrefix); ?>-bar__track" aria-hidden="true">
                        <span class="<?php echo esc_attr($classPrefix); ?>-bar__fill" style="width: <?php echo esc_attr((string) $width); ?>%;"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function label(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    private function formatNumber(float $value): string
    {
        if (floor($value) === $value) {
            return number_format_i18n((int) $value);
        }

        return number_format_i18n($value, 1);
    }
}
