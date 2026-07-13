<?php
/**
 * Plugin Name: APA Agadev
 * Plugin URI: https://agadev.com
 * Description: Plugin WordPress APA Agadev.
 * Version: 2026.7.1
 * Author: Agadev
 * Author URI: https://agadev.com
 * Text Domain: plugin-apa-agadev
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Update URI: https://github.com/Alex-Saba/apa-agadev
 *
 * @package PluginApaAgadev
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PLUGIN_APA_AGADEV_VERSION', '2026.7.1');
define('PLUGIN_APA_AGADEV_FILE', __FILE__);
define('PLUGIN_APA_AGADEV_PATH', plugin_dir_path(__FILE__));
define('PLUGIN_APA_AGADEV_URL', plugin_dir_url(__FILE__));

require_once PLUGIN_APA_AGADEV_PATH . 'includes/class-plugin-apa-agadev-updater.php';

Plugin_Apa_Agadev_Updater::register();

/**
 * Runs when the plugin is activated.
 */
function plugin_apa_agadev_activate(): void
{
    // Activation hook reserved for future setup.
}

/**
 * Runs when the plugin is deactivated.
 */
function plugin_apa_agadev_deactivate(): void
{
    // Deactivation hook reserved for future cleanup.
}

register_activation_hook(__FILE__, 'plugin_apa_agadev_activate');
register_deactivation_hook(__FILE__, 'plugin_apa_agadev_deactivate');
