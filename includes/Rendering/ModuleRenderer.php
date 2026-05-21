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
                <?php echo $this->barsFromModule($module, $classPrefix); ?>
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
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $module
     */
    private function barsFromModule(array $module, string $classPrefix): string
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
}
