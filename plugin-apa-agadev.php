<?php
/**
 * Plugin Name: APA Agadev
 * Plugin URI: https://agadev.com
 * Description: Plugin WordPress APA Agadev.
 * Version: 2026.7.11
 * Author: ACL
 * Author URI: https://agadev.com
 * Text Domain: plugin-apa-agadev
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Update URI: https://github.com/Alex-Saba/apa-agadev
 * Requires Plugins: acl-flows-for-wordpress
 *
 * @package PluginApaAgadev
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PLUGIN_APA_AGADEV_VERSION', '2026.7.11');
define('PLUGIN_APA_AGADEV_FILE', __FILE__);
define('PLUGIN_APA_AGADEV_PATH', plugin_dir_path(__FILE__));
define('PLUGIN_APA_AGADEV_URL', plugin_dir_url(__FILE__));

require_once PLUGIN_APA_AGADEV_PATH . 'includes/class-plugin-apa-agadev-updater.php';

Plugin_Apa_Agadev_Updater::register();

$plugin_apa_agadev_autoload = PLUGIN_APA_AGADEV_PATH . 'vendor/autoload.php';

if (file_exists($plugin_apa_agadev_autoload)) {
    require_once $plugin_apa_agadev_autoload;
} else {
    // Keep the plugin usable when the distributable does not include Composer's vendor directory.
    spl_autoload_register(static function (string $class): void {
        $prefix = 'PluginApaAgadev\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $relative_class = substr($class, strlen($prefix));
        $path = PLUGIN_APA_AGADEV_PATH . 'src/' . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($path)) {
            require_once $path;
        }
    });
}

add_action('plugins_loaded', static function (): void {
    $plugin = new \PluginApaAgadev\Plugin();
    $plugin->register();
});

/**
 * Runs when the plugin is activated.
 */
function plugin_apa_agadev_activate(): void
{
    $data = new \PluginApaAgadev\Service\MaivouDataService();
    $lot_sync = new \PluginApaAgadev\Service\LotSyncService($data);
    $lot_sync->registerPostType();
    $lot_sync->registerRewriteRules();
    \PluginApaAgadev\Service\LotSyncService::schedule();
    flush_rewrite_rules();
}

/**
 * Runs when the plugin is deactivated.
 */
function plugin_apa_agadev_deactivate(): void
{
    \PluginApaAgadev\Service\LotSyncService::unschedule();
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'plugin_apa_agadev_activate');
register_deactivation_hook(__FILE__, 'plugin_apa_agadev_deactivate');
