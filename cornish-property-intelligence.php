<?php
/**
 * Plugin Name: Cornish Property Intelligence
 * Description: Reads Cornish Property public-safe static JSON exports for WordPress proof-of-concept rendering.
 * Version: 0.1.0
 * Author: Sanders Design
 * Requires PHP: 8.1
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('CPI_PLUGIN_FILE', __FILE__);
define('CPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CPI_PLUGIN_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'CornishPropertyIntelligence\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = CPI_PLUGIN_DIR.'includes/'.str_replace('\\', '/', $relative).'.php';

    if (is_readable($path)) {
        require_once $path;
    }
});

register_activation_hook(__FILE__, static function (): void {
    CornishPropertyIntelligence\Plugin::activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules();
});

add_action('plugins_loaded', static function (): void {
    CornishPropertyIntelligence\Plugin::boot();
});
