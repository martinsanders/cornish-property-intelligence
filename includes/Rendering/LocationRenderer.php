<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Rendering;

final class LocationRenderer
{
    public function __construct(
        private readonly ModuleRenderer $modules,
        private readonly NoticeRenderer $notices,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function title(array $payload): string
    {
        $title = $this->text($payload['public_label'] ?? $payload['location']['name'] ?? '');

        if ($title === '') {
            return $this->notices->render('Location data is not available yet.', 'cpi-location-unavailable');
        }

        return '<span class="cpi-location-title">'.esc_html($title).'</span>';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function hero(array $payload): string
    {
        $summary = $this->text($payload['summary'] ?? '');

        ob_start();
        ?>
        <section class="cpi-location-hero-block">
            <p class="cpi-virtual-page__eyebrow"><?php echo esc_html__('Location Intelligence', 'cornish-property-intelligence'); ?></p>
            <h1 class="cpi-virtual-page__title"><?php echo $this->title($payload); ?></h1>

            <?php if ($summary !== '') : ?>
                <p class="cpi-location-hero-block__summary"><?php echo esc_html($summary); ?></p>
            <?php endif; ?>

            <ul class="cpi-location-hero-block__chips" aria-label="<?php echo esc_attr__('Evidence families', 'cornish-property-intelligence'); ?>">
                <?php foreach (['Market sales', 'Planning', 'Building Control', 'Competent Person', 'EPC / Retrofit'] as $chip) : ?>
                    <li><?php echo esc_html($chip); ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="cpi-location-hero-block__actions">
                <a class="cpi-button cpi-button--primary wp-element-button" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php echo esc_html__('Get your market report', 'cornish-property-intelligence'); ?>
                </a>
                <a class="cpi-button cpi-button--secondary wp-element-button" href="<?php echo esc_url(home_url('/locations/')); ?>">
                    <?php echo esc_html__('All location insights', 'cornish-property-intelligence'); ?>
                </a>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function summary(array $payload): string
    {
        $summary = $this->text($payload['summary'] ?? '');
        $intro = is_array($payload['intro'] ?? null) ? $payload['intro'] : [];
        $heading = $this->text($intro['heading'] ?? '');
        $body = $this->text($intro['body'] ?? '');

        if ($summary === '' && $heading === '' && $body === '') {
            return '';
        }

        ob_start();
        ?>
        <section class="cpi-summary cpi-location-summary">
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
     * @param array<string, mixed> $payload
     */
    public function localContext(array $payload): string
    {
        $intro = is_array($payload['intro'] ?? null) ? $payload['intro'] : [];
        $heading = $this->text($intro['heading'] ?? '');
        $body = $this->text($intro['body'] ?? '');
        $note = $this->text($payload['local_context_note'] ?? '');

        if ($heading === '' && $body === '' && $note === '') {
            return '';
        }

        ob_start();
        ?>
        <section class="cpi-location-local-context">
            <article class="cpi-location-local-context__main">
                <p class="cpi-virtual-page__eyebrow"><?php echo esc_html__('Local context', 'cornish-property-intelligence'); ?></p>
                <?php if ($heading !== '') : ?>
                    <h2><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($body !== '') : ?>
                    <p><?php echo esc_html($body); ?></p>
                <?php endif; ?>
            </article>

            <?php if ($note !== '') : ?>
                <aside class="cpi-location-local-context__note">
                    <p class="cpi-virtual-page__eyebrow"><?php echo esc_html__('Local note', 'cornish-property-intelligence'); ?></p>
                    <p><?php echo esc_html($note); ?></p>
                </aside>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function modules(array $payload): string
    {
        $modules = is_array($payload['modules'] ?? null) ? $this->publicEvidenceModules($payload['modules'], $payload) : [];
        $articles = array_key_exists('published_articles', $modules) ? '' : $this->associatedArticles($payload);

        if ($modules === [] && $articles === '') {
            return '';
        }

        return $this->modules->render($modules, 'cpi-location').$articles;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function campaignCta(array $payload): string
    {
        $location = $this->text($payload['public_label'] ?? $payload['location']['name'] ?? 'this location');

        ob_start();
        ?>
        <section class="cpi-location-campaign-cta">
            <div>
                <h2><?php echo esc_html__('Need this view for campaign planning?', 'cornish-property-intelligence'); ?></h2>
                <p><?php echo esc_html(sprintf('Use the %s view to shape a briefing pack, then move into paid intelligence or managed Sanders Design support for deeper planning.', $location)); ?></p>
            </div>
            <div class="cpi-location-campaign-cta__actions">
                <a class="cpi-button cpi-button--secondary wp-element-button" href="<?php echo esc_url(home_url('/locations/')); ?>">
                    <?php echo esc_html__('View paid intelligence', 'cornish-property-intelligence'); ?>
                </a>
                <a class="cpi-button cpi-button--primary wp-element-button" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php echo esc_html__('Read articles', 'cornish-property-intelligence'); ?>
                </a>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Layout-only modules are already represented by the virtual page shell.
     *
     * @param array<string|int, mixed> $modules
     * @param array<string, mixed> $payload
     * @return array<string|int, mixed>
     */
    private function publicEvidenceModules(array $modules, array $payload): array
    {
        $hiddenTypes = ['hero', 'cta'];

        $filtered = array_filter(
            $modules,
            function (mixed $module, string|int $key) use ($hiddenTypes): bool {
                $type = is_array($module) ? $this->text($module['module_type'] ?? $key) : (string) $key;

                return ! in_array($type, $hiddenTypes, true);
            },
            ARRAY_FILTER_USE_BOTH,
        );

        foreach ($filtered as $key => $module) {
            if (! is_array($module)) {
                continue;
            }

            $type = $this->text($module['module_type'] ?? $key);

            if ($type === 'executive_answer' && $this->isGenericModuleHeadline($module)) {
                $summary = $this->text($payload['summary'] ?? '');

                if ($summary !== '') {
                    $module['headline'] = $summary;
                    $module['coverage_note'] = '';
                    $module['privacy_note'] = '';
                    $module['executive_signals'] = $this->executiveSignals($modules);
                    $filtered[$key] = $module;
                }
            }
        }

        return $filtered;
    }

    /**
     * @param array<string|int, mixed> $modules
     * @return array<int, array<string, string>>
     */
    private function executiveSignals(array $modules): array
    {
        $signals = [];
        $map = [
            'trade_work_activity' => [
                'key' => 'trade',
                'label' => 'Work activity signals available',
                'title' => 'Work activity signal available',
            ],
            'market' => [
                'key' => 'market',
                'label' => 'Market signal available',
                'title' => 'Market signal available',
            ],
            'epc_status' => [
                'key' => 'epc',
                'label' => 'EPC / retrofit signals available',
                'title' => 'EPC / retrofit signal available',
            ],
        ];

        foreach ($map as $type => $copy) {
            $module = $modules[$type] ?? null;

            if (! is_array($module)) {
                continue;
            }

            $headline = $this->text($module['headline'] ?? '');

            if ($headline === '') {
                continue;
            }

            $signals[] = [
                'key' => $copy['key'],
                'label' => $copy['label'],
                'title' => $copy['title'],
                'summary' => $headline,
            ];
        }

        return $signals;
    }

    /**
     * @param array<string, mixed> $module
     */
    private function isGenericModuleHeadline(array $module): bool
    {
        $headline = strtolower($this->text($module['headline'] ?? ''));

        return $headline === ''
            || str_contains($headline, 'public-safe content')
            || str_contains($headline, 'layout structure only');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function privacyNote(array $payload): string
    {
        $note = $this->text($payload['privacy_note'] ?? $payload['local_context_note'] ?? '');

        if ($note === '') {
            $note = 'This view uses approved public-safe area intelligence only.';
        }

        return '<p class="cpi-location-privacy-note cpi-evidence-note">'.esc_html($note).'</p>';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function associatedArticles(array $payload): string
    {
        $articles = is_array($payload['associated_articles'] ?? null)
            ? $payload['associated_articles']
            : (is_array($payload['articles'] ?? null) ? $payload['articles'] : []);

        if ($articles === []) {
            return '';
        }

        ob_start();
        ?>
        <section class="cpi-associated-content cpi-location-associated-content">
            <h3><?php echo esc_html__('Associated guides', 'cornish-property-intelligence'); ?></h3>
            <ul class="cpi-links">
                <?php foreach ($articles as $article) : ?>
                    <?php
                    $title = is_array($article) ? $this->text($article['title'] ?? $article['label'] ?? '') : $this->text($article);
                    $url = is_array($article) ? $this->text($article['url'] ?? $article['href'] ?? '') : '';
                    ?>
                    <?php if ($title !== '') : ?>
                        <li>
                            <?php if ($url !== '') : ?>
                                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($title); ?>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
