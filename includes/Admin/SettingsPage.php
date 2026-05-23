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
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_cornish-property-intelligence') {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style(
            'cornish-property-intelligence-admin',
            CPI_PLUGIN_URL.'assets/admin.css',
            ['wp-color-picker'],
            '0.1.0'
        );
        wp_enqueue_script(
            'cornish-property-intelligence-admin',
            CPI_PLUGIN_URL.'assets/admin.js',
            ['wp-color-picker'],
            '0.1.0',
            true
        );
        wp_localize_script('cornish-property-intelligence-admin', 'cpiAdminDesign', [
            'palette' => $this->themePalette(),
        ]);
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
            'design_primary_color' => $this->sanitizeColor($input['design_primary_color'] ?? $current['design_primary_color']),
            'design_accent_color' => $this->sanitizeColor($input['design_accent_color'] ?? $current['design_accent_color']),
            'design_background_color' => $this->sanitizeColor($input['design_background_color'] ?? $current['design_background_color']),
            'design_surface_color' => $this->sanitizeColor($input['design_surface_color'] ?? $current['design_surface_color']),
            'design_text_color' => $this->sanitizeColor($input['design_text_color'] ?? $current['design_text_color']),
            'design_muted_text_color' => $this->sanitizeColor($input['design_muted_text_color'] ?? $current['design_muted_text_color']),
            'design_chart_series_one_color' => $this->sanitizeColor($input['design_chart_series_one_color'] ?? $current['design_chart_series_one_color']),
            'design_chart_series_two_color' => $this->sanitizeColor($input['design_chart_series_two_color'] ?? $current['design_chart_series_two_color']),
            'design_chart_series_three_color' => $this->sanitizeColor($input['design_chart_series_three_color'] ?? $current['design_chart_series_three_color']),
            'design_chart_series_four_color' => $this->sanitizeColor($input['design_chart_series_four_color'] ?? $current['design_chart_series_four_color']),
            'design_chart_series_five_color' => $this->sanitizeColor($input['design_chart_series_five_color'] ?? $current['design_chart_series_five_color']),
            'design_chart_grid_color' => $this->sanitizeColor($input['design_chart_grid_color'] ?? $current['design_chart_grid_color']),
            'design_market_sales_color' => $this->sanitizeColor($input['design_market_sales_color'] ?? $current['design_market_sales_color']),
            'design_market_comparison_sales_color' => $this->sanitizeColor($input['design_market_comparison_sales_color'] ?? $current['design_market_comparison_sales_color']),
            'design_market_price_color' => $this->sanitizeColor($input['design_market_price_color'] ?? $current['design_market_price_color']),
            'design_market_comparison_price_color' => $this->sanitizeColor($input['design_market_comparison_price_color'] ?? $current['design_market_comparison_price_color']),
            'design_heading_font' => sanitize_text_field((string) ($input['design_heading_font'] ?? $current['design_heading_font'])),
            'design_body_font' => sanitize_text_field((string) ($input['design_body_font'] ?? $current['design_body_font'])),
            'design_radius' => $this->sanitizeCssSize($input['design_radius'] ?? $current['design_radius']),
            'design_button_radius' => $this->sanitizeCssSize($input['design_button_radius'] ?? $current['design_button_radius']),
            'design_container_width' => $this->sanitizeCssSize($input['design_container_width'] ?? $current['design_container_width']),
        ];
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cornish-property-intelligence'));
        }

        $activeTab = $this->activeTab();
        $settings = Plugin::settings();
        $notice = null;
        $manifest = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['cpi_save_settings']) || isset($_POST['cpi_test_manifest']))) {
            check_admin_referer('cpi_test_manifest');

            $settings['manifest_path'] = sanitize_text_field((string) ($_POST[Plugin::OPTION_NAME]['manifest_path'] ?? $settings['manifest_path']));
            $settings['design_primary_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_primary_color'] ?? $settings['design_primary_color']);
            $settings['design_accent_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_accent_color'] ?? $settings['design_accent_color']);
            $settings['design_background_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_background_color'] ?? $settings['design_background_color']);
            $settings['design_surface_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_surface_color'] ?? $settings['design_surface_color']);
            $settings['design_text_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_text_color'] ?? $settings['design_text_color']);
            $settings['design_muted_text_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_muted_text_color'] ?? $settings['design_muted_text_color']);
            $settings['design_chart_series_one_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_chart_series_one_color'] ?? $settings['design_chart_series_one_color']);
            $settings['design_chart_series_two_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_chart_series_two_color'] ?? $settings['design_chart_series_two_color']);
            $settings['design_chart_series_three_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_chart_series_three_color'] ?? $settings['design_chart_series_three_color']);
            $settings['design_chart_series_four_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_chart_series_four_color'] ?? $settings['design_chart_series_four_color']);
            $settings['design_chart_series_five_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_chart_series_five_color'] ?? $settings['design_chart_series_five_color']);
            $settings['design_chart_grid_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_chart_grid_color'] ?? $settings['design_chart_grid_color']);
            $settings['design_market_sales_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_market_sales_color'] ?? $settings['design_market_sales_color']);
            $settings['design_market_comparison_sales_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_market_comparison_sales_color'] ?? $settings['design_market_comparison_sales_color']);
            $settings['design_market_price_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_market_price_color'] ?? $settings['design_market_price_color']);
            $settings['design_market_comparison_price_color'] = $this->sanitizeColor($_POST[Plugin::OPTION_NAME]['design_market_comparison_price_color'] ?? $settings['design_market_comparison_price_color']);
            $settings['design_heading_font'] = sanitize_text_field((string) ($_POST[Plugin::OPTION_NAME]['design_heading_font'] ?? $settings['design_heading_font']));
            $settings['design_body_font'] = sanitize_text_field((string) ($_POST[Plugin::OPTION_NAME]['design_body_font'] ?? $settings['design_body_font']));
            $settings['design_radius'] = $this->sanitizeCssSize($_POST[Plugin::OPTION_NAME]['design_radius'] ?? $settings['design_radius']);
            $settings['design_button_radius'] = $this->sanitizeCssSize($_POST[Plugin::OPTION_NAME]['design_button_radius'] ?? $settings['design_button_radius']);
            $settings['design_container_width'] = $this->sanitizeCssSize($_POST[Plugin::OPTION_NAME]['design_container_width'] ?? $settings['design_container_width']);

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

            <nav class="nav-tab-wrapper cpi-settings-tabs" aria-label="<?php echo esc_attr__('Cornish Property Intelligence settings sections', 'cornish-property-intelligence'); ?>">
                <a class="nav-tab <?php echo $activeTab === 'general' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->tabUrl('general')); ?>">
                    <?php echo esc_html__('General', 'cornish-property-intelligence'); ?>
                </a>
                <a class="nav-tab <?php echo $activeTab === 'design' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->tabUrl('design')); ?>">
                    <?php echo esc_html__('Design', 'cornish-property-intelligence'); ?>
                </a>
            </nav>

            <form method="post" action="">
                <?php wp_nonce_field('cpi_test_manifest'); ?>

                <?php if ($activeTab === 'design') : ?>
                    <?php $this->renderDesignTab($settings); ?>
                <?php else : ?>
                    <?php $this->renderGeneralTab($settings, $manifest); ?>
                <?php endif; ?>

                <?php submit_button(__('Save Settings', 'cornish-property-intelligence'), 'secondary', 'cpi_save_settings', false); ?>
                <?php if ($activeTab === 'general') : ?>
                    <?php submit_button(__('Test Manifest', 'cornish-property-intelligence'), 'primary', 'cpi_test_manifest', false, ['style' => 'margin-left: 8px;']); ?>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    /**
     * @param array<string, string> $settings
     * @param array<string, mixed>|null $manifest
     */
    private function renderGeneralTab(array $settings, ?array $manifest): void
    {
        ?>
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
        <?php
    }

    /**
     * @param array<string, string> $settings
     */
    private function renderDesignTab(array $settings): void
    {
        ?>
        <h2><?php echo esc_html__('Design Bridge', 'cornish-property-intelligence'); ?></h2>
        <p>
            <?php echo esc_html__('Frontend blocks inherit WordPress/Kadence global theme colours, typography and spacing tokens where available. Use this tab only to smooth out plugin components that still need a manual bridge to the active theme.', 'cornish-property-intelligence'); ?>
        </p>
        <p class="description">
            <?php echo esc_html__('Colour fields use the native WordPress colour picker. The picker palette includes Kadence/theme colours when available.', 'cornish-property-intelligence'); ?>
        </p>
        <table class="form-table cpi-design-settings" role="presentation">
            <tbody>
            <?php $this->renderColorField($settings, 'design_primary_color', 'Primary/accent colour', 'Used for buttons, selected controls and chart primary series. Leave empty to inherit the theme primary palette.'); ?>
            <?php $this->renderColorField($settings, 'design_accent_color', 'Secondary accent colour', 'Used for soft chart series, notices and emphasis panels. Leave empty to inherit the theme secondary/accent palette.'); ?>
            <?php $this->renderColorField($settings, 'design_background_color', 'Page background colour', 'Leave empty to inherit the theme base/background colour.'); ?>
            <?php $this->renderColorField($settings, 'design_surface_color', 'Panel/card surface colour', 'Leave empty to inherit the theme content background colour.'); ?>
            <?php $this->renderColorField($settings, 'design_text_color', 'Main text colour', 'Leave empty to inherit the theme body text colour.'); ?>
            <?php $this->renderColorField($settings, 'design_muted_text_color', 'Muted text colour', 'Leave empty to inherit the theme muted text colour.'); ?>
            <?php $this->renderColorField($settings, 'design_chart_series_one_color', 'Chart series 1 colour', 'Used for the first series in source, rating and distribution charts. Leave empty to use the plugin chart palette.'); ?>
            <?php $this->renderColorField($settings, 'design_chart_series_two_color', 'Chart series 2 colour', 'Used for the second series in source, rating and distribution charts. Leave empty to use the plugin chart palette.'); ?>
            <?php $this->renderColorField($settings, 'design_chart_series_three_color', 'Chart series 3 colour', 'Used for the third series in source, rating and distribution charts. Leave empty to use the plugin chart palette.'); ?>
            <?php $this->renderColorField($settings, 'design_chart_series_four_color', 'Chart series 4 colour', 'Used for the fourth series in source, rating and distribution charts. Leave empty to use the plugin chart palette.'); ?>
            <?php $this->renderColorField($settings, 'design_chart_series_five_color', 'Chart series 5 colour', 'Used for the fifth series in source, rating and distribution charts. Leave empty to use the plugin chart palette.'); ?>
            <?php $this->renderColorField($settings, 'design_chart_grid_color', 'Chart grid line colour', 'Used for chart grid and axis guide lines. Leave empty to use the plugin chart palette.'); ?>
            <?php $this->renderColorField($settings, 'design_market_sales_color', 'Market chart sales colour', 'Used for the selected period sales line in Market monthly comparison charts. Leave empty to use the plugin chart palette.'); ?>
            <?php $this->renderColorField($settings, 'design_market_comparison_sales_color', 'Market chart comparison sales colour', 'Used for previous period or same period last year sales lines in Market charts. Leave empty to use the plugin chart palette.'); ?>
            <?php $this->renderColorField($settings, 'design_market_price_color', 'Market chart median price colour', 'Used for current median price lines in Market monthly comparison charts. Leave empty to use the plugin chart palette.'); ?>
            <?php $this->renderColorField($settings, 'design_market_comparison_price_color', 'Market chart comparison price colour', 'Used for previous period or same period last year median price lines in Market charts. Leave empty to use the plugin chart palette.'); ?>
            <tr>
                <th scope="row"><label for="cpi_design_heading_font"><?php echo esc_html__('Heading font stack', 'cornish-property-intelligence'); ?></label></th>
                <td>
                    <input id="cpi_design_heading_font" name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[design_heading_font]" type="text" class="regular-text code" value="<?php echo esc_attr($settings['design_heading_font']); ?>" placeholder="inherit">
                    <p class="description"><?php echo esc_html__('Optional CSS font-family value for plugin headings. Empty inherits the theme heading font.', 'cornish-property-intelligence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cpi_design_body_font"><?php echo esc_html__('Body font stack', 'cornish-property-intelligence'); ?></label></th>
                <td>
                    <input id="cpi_design_body_font" name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[design_body_font]" type="text" class="regular-text code" value="<?php echo esc_attr($settings['design_body_font']); ?>" placeholder="inherit">
                    <p class="description"><?php echo esc_html__('Optional CSS font-family value for plugin body text. Empty inherits the active theme.', 'cornish-property-intelligence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cpi_design_radius"><?php echo esc_html__('Panel radius', 'cornish-property-intelligence'); ?></label></th>
                <td>
                    <input id="cpi_design_radius" name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[design_radius]" type="text" class="small-text code" value="<?php echo esc_attr($settings['design_radius']); ?>" placeholder="8px">
                    <p class="description"><?php echo esc_html__('Optional CSS size such as 8px or 0.5rem. Empty uses the theme/plugin default.', 'cornish-property-intelligence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cpi_design_button_radius"><?php echo esc_html__('Button radius', 'cornish-property-intelligence'); ?></label></th>
                <td>
                    <input id="cpi_design_button_radius" name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[design_button_radius]" type="text" class="small-text code" value="<?php echo esc_attr($settings['design_button_radius']); ?>" placeholder="100px">
                    <p class="description"><?php echo esc_html__('Optional CSS size for plugin action buttons. Empty follows the active theme bridge and uses a pill radius fallback.', 'cornish-property-intelligence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cpi_design_container_width"><?php echo esc_html__('Content width', 'cornish-property-intelligence'); ?></label></th>
                <td>
                    <input id="cpi_design_container_width" name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[design_container_width]" type="text" class="small-text code" value="<?php echo esc_attr($settings['design_container_width']); ?>" placeholder="76rem">
                    <p class="description"><?php echo esc_html__('Optional CSS width such as 76rem or 1200px for virtual route content. Empty inherits the plugin/theme bridge default.', 'cornish-property-intelligence'); ?></p>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param array<string, string> $settings
     */
    private function renderColorField(array $settings, string $key, string $label, string $description): void
    {
        ?>
        <tr>
            <th scope="row"><label for="cpi_<?php echo esc_attr($key); ?>"><?php echo esc_html__($label, 'cornish-property-intelligence'); ?></label></th>
            <td>
                <input id="cpi_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(Plugin::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]" type="text" class="regular-text code cpi-color-picker" value="<?php echo esc_attr($settings[$key]); ?>" placeholder="#245a70" data-cpi-design-field="<?php echo esc_attr($key); ?>">
                <p class="description"><?php echo esc_html__($description, 'cornish-property-intelligence'); ?></p>
            </td>
        </tr>
        <?php
    }

    private function activeTab(): string
    {
        $tab = sanitize_key((string) ($_GET['tab'] ?? 'general'));

        return in_array($tab, ['general', 'design'], true) ? $tab : 'general';
    }

    private function tabUrl(string $tab): string
    {
        return add_query_arg(
            [
                'page' => 'cornish-property-intelligence',
                'tab' => $tab,
            ],
            admin_url('options-general.php')
        );
    }

    private function sanitizeColor(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $hex = sanitize_hex_color($value);

        return is_string($hex) ? $hex : '';
    }

    private function sanitizeCssSize(mixed $value): string
    {
        $value = trim(sanitize_text_field((string) $value));

        if ($value === '') {
            return '';
        }

        return preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|%)$/', $value) === 1 ? $value : '';
    }

    /**
     * @return array<int, array{label: string, color: string}>
     */
    private function themePalette(): array
    {
        $palette = [];
        $seen = [];

        $addColor = static function (string $label, mixed $color) use (&$palette, &$seen): void {
            $color = sanitize_hex_color(is_string($color) ? $color : '');

            if (! is_string($color) || $color === '') {
                return;
            }

            $key = strtolower($color);

            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $palette[] = [
                'label' => sanitize_text_field($label),
                'color' => $color,
            ];
        };

        if (wp_get_theme()->get_template() === 'kadence') {
            foreach ($this->kadenceDefaultPalette() as $label => $color) {
                $addColor($label, $color);
            }
        }

        if (function_exists('wp_get_global_settings')) {
            $settings = wp_get_global_settings(['color', 'palette']);

            foreach (['theme', 'custom'] as $paletteSource) {
                if (! isset($settings[$paletteSource]) || ! is_array($settings[$paletteSource])) {
                    continue;
                }

                foreach ($settings[$paletteSource] as $color) {
                    if (! is_array($color)) {
                        continue;
                    }

                    $addColor((string) ($color['name'] ?? $color['slug'] ?? 'Theme colour'), $color['color'] ?? '');
                }
            }
        }

        return $palette;
    }

    /**
     * Kadence exposes these as CSS custom properties on the frontend, but those
     * variables are not always present on wp-admin settings screens.
     *
     * @return array<string, string>
     */
    private function kadenceDefaultPalette(): array
    {
        return [
            'Kadence palette 1' => '#2B6CB0',
            'Kadence palette 2' => '#215387',
            'Kadence palette 3' => '#1A202C',
            'Kadence palette 4' => '#2D3748',
            'Kadence palette 5' => '#4A5568',
            'Kadence palette 6' => '#718096',
            'Kadence palette 7' => '#EDF2F7',
            'Kadence palette 8' => '#F7FAFC',
            'Kadence palette 9' => '#FFFFFF',
            'Kadence success' => '#13612e',
            'Kadence info' => '#1159af',
            'Kadence alert' => '#b82105',
            'Kadence warning' => '#f7630c',
            'Kadence rating' => '#f5a524',
        ];
    }
}
