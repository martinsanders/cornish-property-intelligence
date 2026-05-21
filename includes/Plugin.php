<?php

declare(strict_types=1);

namespace CornishPropertyIntelligence;

use CornishPropertyIntelligence\Admin\SettingsPage;
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
        (new LocationVirtualRoute($locationRepository))->register();
        (new PostcodeAreaVirtualRoute($postcodeAreaRepository))->register();
    }

    public static function enqueueAssets(): void
    {
        wp_enqueue_style(
            'cornish-property-intelligence',
            CPI_PLUGIN_URL.'assets/frontend.css',
            [],
            '0.1.0'
        );
    }

    public static function activate(): void
    {
        self::boot();
        flush_rewrite_rules();
    }

    /**
     * @return array{manifest_path: string, last_checked_at: string, last_status: string, last_version: string}
     */
    public static function settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        if (! is_array($settings)) {
            $settings = [];
        }

        return [
            'manifest_path' => (string) ($settings['manifest_path'] ?? ''),
            'last_checked_at' => (string) ($settings['last_checked_at'] ?? ''),
            'last_status' => (string) ($settings['last_status'] ?? ''),
            'last_version' => (string) ($settings['last_version'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $settings
     */
    public static function updateSettings(array $settings): void
    {
        update_option(self::OPTION_NAME, [
            'manifest_path' => $settings['manifest_path'] ?? '',
            'last_checked_at' => $settings['last_checked_at'] ?? '',
            'last_status' => $settings['last_status'] ?? '',
            'last_version' => $settings['last_version'] ?? '',
        ]);
    }
}
