<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence\Admin;

use CornishPropertyIntelligence\Plugin;
use CornishPropertyIntelligence\PublicData\ManifestRepository;
use Throwable;

final class SettingsPage
{
    public function __construct(
        private readonly ManifestRepository $manifestRepository,
    ) {}

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPage(): void
    {
        add_options_page(
            'Cornish Property Intelligence',
            'Cornish Property Intelligence',
            'manage_options',
            'cornish-property-intelligence',
            [$this, 'render']
        );
    }

    public function registerSettings(): void
    {
        register_setting('cpi_settings_group', Plugin::OPTION_NAME, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeSettings'],
            'default' => [],
        ]);
    }

    /**
     * @param mixed $input
     * @return array<string, string>
     */
    public function sanitizeSettings(mixed $input): array
    {
        $current = Plugin::settings();
        $input = is_array($input) ? $input : [];

        return [
            'manifest_path' => sanitize_text_field((string) ($input['manifest_path'] ?? $current['manifest_path'])),
            'last_checked_at' => $current['last_checked_at'],
            'last_status' => $current['last_status'],
            'last_version' => $current['last_version'],
        ];
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cornish-property-intelligence'));
        }

        $settings = Plugin::settings();
        $notice = null;
        $manifest = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['cpi_save_settings']) || isset($_POST['cpi_test_manifest']))) {
            check_admin_referer('cpi_test_manifest');

            $settings['manifest_path'] = sanitize_text_field((string) ($_POST[Plugin::OPTION_NAME]['manifest_path'] ?? $settings['manifest_path']));

            if (isset($_POST['cpi_test_manifest'])) {
                $settings['last_checked_at'] = current_time('mysql');

                try {
                    $manifest = $this->manifestRepository->readRootManifest($settings['manifest_path']);
                    $settings['last_status'] = 'Manifest read successfully.';
                    $settings['last_version'] = (string) ($manifest['current_version'] ?? '');
                    $notice = ['type' => 'success', 'message' => 'Manifest read successfully.'];
                } catch (Throwable $exception) {
                    $settings['last_status'] = $exception->getMessage();
                    $settings['last_version'] = '';
                    $notice = ['type' => 'error', 'message' => $exception->getMessage()];
                }
            } else {
                $notice = ['type' => 'success', 'message' => 'Settings saved.'];
            }

            Plugin::updateSettings($settings);
        } elseif ($settings['manifest_path'] !== '') {
            try {
                $manifest = $this->manifestRepository->readRootManifest($settings['manifest_path']);
            } catch (Throwable) {
                $manifest = null;
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Cornish Property Intelligence', 'cornish-property-intelligence'); ?></h1>

            <?php if ($notice !== null) : ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <p>
                <?php echo esc_html__('This proof-of-concept reads Laravel public-safe static JSON exports only. It must not connect to the Laravel private database.', 'cornish-property-intelligence'); ?>
            </p>
            <p>
                <?php echo esc_html__('Location data can be placed inside normal WordPress or Kadence-designed pages using Cornish Property dynamic blocks/shortcodes. No manual WordPress page is needed per location.', 'cornish-property-intelligence'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('cpi_test_manifest'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="cpi_manifest_path"><?php echo esc_html__('Manifest local path', 'cornish-property-intelligence'); ?></label>
                        </th>
                        <td>
                            <input
                                id="cpi_manifest_path"
                                name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[manifest_path]"
                                type="text"
                                class="regular-text code"
                                value="<?php echo esc_attr($settings['manifest_path']); ?>"
                                placeholder="/path/to/public-data/exports/manifest.json"
                            >
                            <p class="description">
                                <?php echo esc_html__('Use the Laravel root export manifest path for this local POC.', 'cornish-property-intelligence'); ?>
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'cornish-property-intelligence'), 'secondary', 'cpi_save_settings', false); ?>
                <?php submit_button(__('Test Manifest', 'cornish-property-intelligence'), 'primary', 'cpi_test_manifest', false, ['style' => 'margin-left: 8px;']); ?>
            </form>

            <h2><?php echo esc_html__('Manifest Status', 'cornish-property-intelligence'); ?></h2>
            <table class="widefat striped" style="max-width: 760px;">
                <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Status', 'cornish-property-intelligence'); ?></th>
                    <td><?php echo esc_html($settings['last_status'] ?: 'Not checked yet.'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Current version', 'cornish-property-intelligence'); ?></th>
                    <td><?php echo esc_html((string) ($manifest['current_version'] ?? $settings['last_version'] ?: 'Not available.')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Generated at', 'cornish-property-intelligence'); ?></th>
                    <td><?php echo esc_html((string) ($manifest['generated_at'] ?? 'Not available.')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Base path', 'cornish-property-intelligence'); ?></th>
                    <td><?php echo esc_html((string) ($manifest['base_path'] ?? 'Not available.')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Last checked', 'cornish-property-intelligence'); ?></th>
                    <td><?php echo esc_html($settings['last_checked_at'] ?: 'Not checked yet.'); ?></td>
                </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Routing Note', 'cornish-property-intelligence'); ?></h2>
            <p>
                <?php echo esc_html__('Normal WordPress pages may be used for homepage, /near-me, /locations and /articles index pages. Kadence or the block editor should own layout and visual design, while Cornish Property blocks provide safe JSON-backed data. Later, /locations/{slug}/ and /near-me/{area_key}/ can infer context from virtual routes and render the same block/template approach. Do not create a manual WordPress page per location, postcode sector or postcode district.', 'cornish-property-intelligence'); ?>
            </p>
        </div>
        <?php
    }
}
