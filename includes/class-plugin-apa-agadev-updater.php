<?php
/**
 * GitHub release updater for APA Agadev.
 *
 * @package PluginApaAgadev
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Exposes GitHub releases to WordPress' native plugin updater.
 */
final class Plugin_Apa_Agadev_Updater
{
    private const UPDATE_URI = 'https://github.com/Alex-Saba/apa-agadev';

    private const RELEASE_API_URL = 'https://api.github.com/repos/Alex-Saba/apa-agadev/releases/latest';

    private const PACKAGE_ASSET_NAME = 'plugin-apa-agadev.zip';

    private const PLUGIN_SLUG = 'plugin-apa-agadev';

    private const RELEASE_CACHE_KEY = 'plugin_apa_agadev_latest_github_release';

    /**
     * Registers update discovery and automatic installation hooks.
     */
    public static function register(): void
    {
        add_filter('update_plugins_github.com', [self::class, 'check_for_update'], 10, 4);
        add_filter('auto_update_plugin', [self::class, 'enable_auto_update'], 10, 2);
    }

    /**
     * Returns a WordPress update offer when a newer GitHub release exists.
     *
     * The release must contain an asset named plugin-apa-agadev.zip. Deliberately
     * avoiding the GitHub source archive keeps the installed plugin folder stable.
     *
     * @param array|false $update      Existing update offer.
     * @param array       $plugin_data Parsed plugin headers.
     * @param string      $plugin_file Plugin basename.
     * @param string[]    $locales     Installed locales.
     *
     * @return array|false
     */
    public static function check_for_update($update, array $plugin_data, string $plugin_file, array $locales)
    {
        unset($locales);

        if (plugin_basename(PLUGIN_APA_AGADEV_FILE) !== $plugin_file) {
            return $update;
        }

        $release = self::get_latest_release();

        if (false === $release || version_compare($release['version'], $plugin_data['Version'], '<=')) {
            return false;
        }

        return [
            'id'           => self::UPDATE_URI,
            'slug'         => self::PLUGIN_SLUG,
            'plugin'       => $plugin_file,
            'version'      => $release['version'],
            'new_version'  => $release['version'],
            'url'          => $release['url'],
            'package'      => $release['package'],
            'requires_php' => '8.0',
            'autoupdate'   => true,
        ];
    }

    /**
     * Forces automatic updates only for this plugin.
     *
     * @param bool|null $update Current automatic-update decision.
     * @param object    $item   Update offer.
     *
     * @return bool|null
     */
    public static function enable_auto_update($update, object $item)
    {
        if (isset($item->slug) && self::PLUGIN_SLUG === $item->slug) {
            return true;
        }

        return $update;
    }

    /**
     * Fetches and normalizes the latest public GitHub release.
     *
     * @return array{version: string, url: string, package: string}|false
     */
    private static function get_latest_release()
    {
        $cached_release = get_site_transient(self::RELEASE_CACHE_KEY);

        if (is_array($cached_release)) {
            return $cached_release;
        }

        $response = wp_remote_get(
            self::RELEASE_API_URL,
            [
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'APA-Agadev-WordPress-Updater',
                ],
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($payload) || empty($payload['tag_name']) || empty($payload['html_url'])) {
            return false;
        }

        $package_url = self::find_package_url($payload['assets'] ?? []);

        if (null === $package_url) {
            return false;
        }

        $release = [
            'version' => ltrim(sanitize_text_field($payload['tag_name']), 'vV'),
            'url'     => esc_url_raw($payload['html_url']),
            'package' => $package_url,
        ];

        set_site_transient(self::RELEASE_CACHE_KEY, $release, HOUR_IN_SECONDS);

        return $release;
    }

    /**
     * Finds the release asset built specifically for WordPress installation.
     *
     * @param mixed $assets GitHub release assets.
     */
    private static function find_package_url($assets): ?string
    {
        if (! is_array($assets)) {
            return null;
        }

        foreach ($assets as $asset) {
            if (
                is_array($asset)
                && self::PACKAGE_ASSET_NAME === ($asset['name'] ?? '')
                && ! empty($asset['browser_download_url'])
            ) {
                return esc_url_raw($asset['browser_download_url']);
            }
        }

        return null;
    }
}
