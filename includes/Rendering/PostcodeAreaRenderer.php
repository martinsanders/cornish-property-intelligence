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

        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];

        return $this->modules->render($modules, 'cpi-postcode-area');
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

        if ($guides === []) {
            return '';
        }

        ob_start();
        ?>
        <ul class="cpi-postcode-area-guides cpi-links">
            <?php foreach ($guides as $guide) : ?>
                <?php
                $label = is_array($guide) ? $this->text($guide['title'] ?? $guide['label'] ?? '') : $this->text($guide);
                $url = is_array($guide) ? $this->text($guide['url'] ?? $guide['href'] ?? '') : '';
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
}
