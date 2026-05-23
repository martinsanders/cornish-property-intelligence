<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence;

use CornishPropertyIntelligence\Admin\SettingsPage;
use CornishPropertyIntelligence\Blocks\DynamicBlocks;
use CornishPropertyIntelligence\Frontend\LocationVirtualRoute;
use CornishPropertyIntelligence\Frontend\PostcodeAreaVirtualRoute;
use CornishPropertyIntelligence\PublicData\LocationRepository;
use CornishPropertyIntelligence\PublicData\JsonLoader;
use CornishPropertyIntelligence\PublicData\ManifestRepository;
use CornishPropertyIntelligence\PublicData\PostcodeAreaRepository;
use CornishPropertyIntelligence\Rendering\BarsRenderer;
use CornishPropertyIntelligence\Rendering\LocationRenderer;
use CornishPropertyIntelligence\Rendering\ModuleRenderer;
use CornishPropertyIntelligence\Rendering\NoticeRenderer;
use CornishPropertyIntelligence\Rendering\PostcodeAreaRenderer;
use CornishPropertyIntelligence\Safety\PayloadScanner;
use CornishPropertyIntelligence\Shortcodes\LocationShortcodes;
use CornishPropertyIntelligence\Shortcodes\PostcodeAreaShortcodes;

final class Plugin
{
    public const OPTION_NAME = 'cpi_settings';

    public static function boot(): void
    {
        $scanner = new PayloadScanner();
        $loader = new JsonLoader($scanner);
        $manifestRepository = new ManifestRepository($loader);
        $noticeRenderer = new NoticeRenderer();
        $moduleRenderer = new ModuleRenderer(new BarsRenderer());

        (new SettingsPage($manifestRepository))->register();
        $locationRepository = new LocationRepository($manifestRepository);
        $postcodeAreaRepository = new PostcodeAreaRepository($manifestRepository);
        $locationRenderer = new LocationRenderer($moduleRenderer, $noticeRenderer);
        $postcodeAreaRenderer = new PostcodeAreaRenderer($moduleRenderer, $noticeRenderer);

        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);

        (new LocationShortcodes($locationRepository, $locationRenderer, $noticeRenderer))->register();
        (new PostcodeAreaShortcodes($postcodeAreaRepository, $postcodeAreaRenderer, $noticeRenderer))->register();
        (new DynamicBlocks($locationRepository, $postcodeAreaRepository, $locationRenderer, $postcodeAreaRenderer, $noticeRenderer))->register();
        (new LocationVirtualRoute($locationRepository))->register();
        (new PostcodeAreaVirtualRoute($postcodeAreaRepository))->register();
    }

    public static function enqueueAssets(): void
    {
        wp_enqueue_style(
            'cornish-property-intelligence',
            CPI_PLUGIN_URL.'assets/frontend.css',
            [],
            self::assetVersion('assets/frontend.css')
        );
        wp_add_inline_style('cornish-property-intelligence', self::designCss());

        wp_enqueue_script(
            'echarts',
            'https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js',
            [],
            '5.5.1',
            true
        );

        wp_enqueue_script(
            'cornish-property-intelligence',
            CPI_PLUGIN_URL.'assets/frontend.js',
            ['echarts'],
            self::assetVersion('assets/frontend.js'),
            true
        );
    }

    public static function activate(): void
    {
        self::boot();
        flush_rewrite_rules();
    }

    /**
     * @return array<string, string>
     */
    public static function settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        if (! is_array($settings)) {
            $settings = [];
        }

        return [
            'manifest_path' => (string) ($settings['manifest_path'] ?? ''),
            'location_template_page_id' => (string) absint($settings['location_template_page_id'] ?? 0),
            'near_me_template_page_id' => (string) absint($settings['near_me_template_page_id'] ?? 0),
            'last_checked_at' => (string) ($settings['last_checked_at'] ?? ''),
            'last_status' => (string) ($settings['last_status'] ?? ''),
            'last_version' => (string) ($settings['last_version'] ?? ''),
            'design_primary_color' => (string) ($settings['design_primary_color'] ?? ''),
            'design_accent_color' => (string) ($settings['design_accent_color'] ?? ''),
            'design_background_color' => (string) ($settings['design_background_color'] ?? ''),
            'design_surface_color' => (string) ($settings['design_surface_color'] ?? ''),
            'design_text_color' => (string) ($settings['design_text_color'] ?? ''),
            'design_muted_text_color' => (string) ($settings['design_muted_text_color'] ?? ''),
            'design_chart_series_one_color' => (string) ($settings['design_chart_series_one_color'] ?? ''),
            'design_chart_series_two_color' => (string) ($settings['design_chart_series_two_color'] ?? ''),
            'design_chart_series_three_color' => (string) ($settings['design_chart_series_three_color'] ?? ''),
            'design_chart_series_four_color' => (string) ($settings['design_chart_series_four_color'] ?? ''),
            'design_chart_series_five_color' => (string) ($settings['design_chart_series_five_color'] ?? ''),
            'design_chart_grid_color' => (string) ($settings['design_chart_grid_color'] ?? ''),
            'design_market_sales_color' => (string) ($settings['design_market_sales_color'] ?? ''),
            'design_market_comparison_sales_color' => (string) ($settings['design_market_comparison_sales_color'] ?? ''),
            'design_market_price_color' => (string) ($settings['design_market_price_color'] ?? ''),
            'design_market_comparison_price_color' => (string) ($settings['design_market_comparison_price_color'] ?? ''),
            'design_heading_font' => (string) ($settings['design_heading_font'] ?? ''),
            'design_body_font' => (string) ($settings['design_body_font'] ?? ''),
            'design_radius' => (string) ($settings['design_radius'] ?? ''),
            'design_button_radius' => (string) ($settings['design_button_radius'] ?? ''),
            'design_container_width' => (string) ($settings['design_container_width'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $settings
     */
    public static function updateSettings(array $settings): void
    {
        update_option(self::OPTION_NAME, [
            'manifest_path' => $settings['manifest_path'] ?? '',
            'location_template_page_id' => (string) absint($settings['location_template_page_id'] ?? 0),
            'near_me_template_page_id' => (string) absint($settings['near_me_template_page_id'] ?? 0),
            'last_checked_at' => $settings['last_checked_at'] ?? '',
            'last_status' => $settings['last_status'] ?? '',
            'last_version' => $settings['last_version'] ?? '',
            'design_primary_color' => $settings['design_primary_color'] ?? '',
            'design_accent_color' => $settings['design_accent_color'] ?? '',
            'design_background_color' => $settings['design_background_color'] ?? '',
            'design_surface_color' => $settings['design_surface_color'] ?? '',
            'design_text_color' => $settings['design_text_color'] ?? '',
            'design_muted_text_color' => $settings['design_muted_text_color'] ?? '',
            'design_chart_series_one_color' => $settings['design_chart_series_one_color'] ?? '',
            'design_chart_series_two_color' => $settings['design_chart_series_two_color'] ?? '',
            'design_chart_series_three_color' => $settings['design_chart_series_three_color'] ?? '',
            'design_chart_series_four_color' => $settings['design_chart_series_four_color'] ?? '',
            'design_chart_series_five_color' => $settings['design_chart_series_five_color'] ?? '',
            'design_chart_grid_color' => $settings['design_chart_grid_color'] ?? '',
            'design_market_sales_color' => $settings['design_market_sales_color'] ?? '',
            'design_market_comparison_sales_color' => $settings['design_market_comparison_sales_color'] ?? '',
            'design_market_price_color' => $settings['design_market_price_color'] ?? '',
            'design_market_comparison_price_color' => $settings['design_market_comparison_price_color'] ?? '',
            'design_heading_font' => $settings['design_heading_font'] ?? '',
            'design_body_font' => $settings['design_body_font'] ?? '',
            'design_radius' => $settings['design_radius'] ?? '',
            'design_button_radius' => $settings['design_button_radius'] ?? '',
            'design_container_width' => $settings['design_container_width'] ?? '',
        ]);
    }

    private static function designCss(): string
    {
        $settings = self::settings();
        $variables = [
            '--cpi-color-primary' => $settings['design_primary_color'],
            '--cpi-color-accent' => $settings['design_accent_color'],
            '--cpi-color-page' => $settings['design_background_color'],
            '--cpi-color-surface' => $settings['design_surface_color'],
            '--cpi-color-ink' => $settings['design_text_color'],
            '--cpi-color-muted' => $settings['design_muted_text_color'],
            '--cpi-chart-series-one-color' => $settings['design_chart_series_one_color'],
            '--cpi-chart-series-two-color' => $settings['design_chart_series_two_color'],
            '--cpi-chart-series-three-color' => $settings['design_chart_series_three_color'],
            '--cpi-chart-series-four-color' => $settings['design_chart_series_four_color'],
            '--cpi-chart-series-five-color' => $settings['design_chart_series_five_color'],
            '--cpi-chart-grid-color' => $settings['design_chart_grid_color'],
            '--cpi-chart-market-sales-color' => $settings['design_market_sales_color'],
            '--cpi-chart-market-comparison-sales-color' => $settings['design_market_comparison_sales_color'],
            '--cpi-chart-market-price-color' => $settings['design_market_price_color'],
            '--cpi-chart-market-comparison-price-color' => $settings['design_market_comparison_price_color'],
            '--cpi-heading-font' => $settings['design_heading_font'],
            '--cpi-body-font' => $settings['design_body_font'],
            '--cpi-radius' => $settings['design_radius'],
            '--cpi-button-radius' => $settings['design_button_radius'],
            '--cpi-container-width' => $settings['design_container_width'],
        ];
        $declarations = [];

        foreach ($variables as $name => $value) {
            $value = self::safeCssValue((string) $value);

            if ($value !== '') {
                $declarations[] = $name.': '.$value.';';
            }
        }

        if ($declarations === []) {
            return '';
        }

        return ':root {'."\n    ".implode("\n    ", $declarations)."\n".'}';
    }

    public static function assetVersion(string $path): string
    {
        $file = CPI_PLUGIN_DIR.$path;

        return is_readable($file) ? (string) filemtime($file) : '0.1.0';
    }

    private static function safeCssValue(string $value): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/[;{}]/', $value) === 1) {
            return '';
        }

        return $value;
    }
}
