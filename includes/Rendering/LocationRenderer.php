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
    public function modules(array $payload): string
    {
        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
        $articles = $this->associatedArticles($payload);

        if ($modules === [] && $articles === '') {
            return '';
        }

        return $this->modules->render($modules, 'cpi-location').$articles;
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
