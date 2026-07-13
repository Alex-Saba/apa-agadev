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
 * Integrates GitHub releases with WordPress' native plugin updater.
 */
final class Plugin_Apa_Agadev_Updater
{
    private const GITHUB_REPOSITORY = 'Alex-Saba/apa-agadev';

    private const PLUGIN_NAME = 'APA Agadev';

    private const PLUGIN_SLUG = 'plugin-apa-agadev';

    /**
     * Registers the update discovery, information and installation hooks.
     */
    public static function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_for_updates']);
        add_filter('plugins_api', [self::class, 'plugins_api'], 10, 3);
        add_filter('upgrader_post_install', [self::class, 'after_upgrade'], 10, 3);
        add_filter('auto_update_plugin', [self::class, 'allow_auto_update'], 10, 2);
    }

    /**
     * Adds the latest GitHub release to WordPress' plugin update transient.
     *
     * @param mixed $transient Plugin update transient.
     *
     * @return mixed
     */
    public static function check_for_updates($transient)
    {
        if (! is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $current_version = self::get_plugin_version();
        $release = self::get_latest_release();

        if ('' === $current_version || null === $release) {
            return $transient;
        }

        $latest_version = ltrim((string) ($release['tag_name'] ?? ''), 'vV');

        if ('' === $latest_version || ! version_compare($latest_version, $current_version, '>')) {
            return $transient;
        }

        $plugin_basename = self::get_plugin_basename();
        $transient->response[$plugin_basename] = (object) [
            'slug'        => self::PLUGIN_SLUG,
            'plugin'      => $plugin_basename,
            'new_version' => $latest_version,
            'package'     => self::get_github_zipball_url((string) ($release['tag_name'] ?? '')),
            'url'         => self::get_repository_url(),
        ];

        return $transient;
    }

    /**
     * Supplies the release details displayed by WordPress.
     *
     * @param mixed  $result Existing plugin API result.
     * @param string $action Requested plugin API action.
     * @param mixed  $args   Plugin API arguments.
     *
     * @return mixed
     */
    public static function plugins_api($result, string $action, $args)
    {
        if ('plugin_information' !== $action || empty($args->slug) || self::PLUGIN_SLUG !== $args->slug) {
            return $result;
        }

        $release = self::get_latest_release();

        if (null === $release) {
            return $result;
        }

        $tag = (string) ($release['tag_name'] ?? '');

        return (object) [
            'name'          => self::PLUGIN_NAME,
            'slug'          => self::PLUGIN_SLUG,
            'version'       => ltrim($tag, 'vV'),
            'author'        => 'ACL',
            'homepage'      => self::get_repository_url(),
            'download_link' => self::get_github_zipball_url($tag),
            'sections'      => [
                'description' => $release['body'] ?? 'Mise a jour via GitHub.',
            ],
        ];
    }

    /**
     * Restores the stable plugin directory after GitHub's zipball is extracted.
     *
     * @param mixed $response   Upgrader response.
     * @param array $hook_extra Upgrader context.
     * @param array $result     Installation result.
     *
     * @return mixed
     */
    public static function after_upgrade($response, array $hook_extra, array $result)
    {
        if (empty($hook_extra['plugin']) || self::get_plugin_basename() !== $hook_extra['plugin']) {
            return $response;
        }

        $destination = (string) ($result['destination'] ?? '');

        if ('' === $destination) {
            return $response;
        }

        $plugin_directory = WP_PLUGIN_DIR . '/' . dirname(self::get_plugin_basename());

        if (self::move_folder($destination, $plugin_directory)) {
            activate_plugin(self::get_plugin_basename());
        }

        return $response;
    }

    /**
     * Enables automatic installation only for APA Agadev updates.
     *
     * @param bool|null $update Current automatic-update decision.
     * @param object    $item   Update offer.
     *
     * @return bool|null
     */
    public static function allow_auto_update($update, object $item)
    {
        if (! empty($item->plugin) && self::get_plugin_basename() === $item->plugin) {
            return true;
        }

        return $update;
    }

    /**
     * Fetches the latest public GitHub release.
     */
    private static function get_latest_release(): ?array
    {
        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::GITHUB_REPOSITORY . '/releases/latest',
            [
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'APA-Agadev-WordPress-Updater',
                ],
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status < 200 || $status >= 300) {
            return null;
        }

        $release = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($release) ? $release : null;
    }

    /**
     * Builds the GitHub zipball URL for a release tag.
     */
    private static function get_github_zipball_url(string $tag): string
    {
        $url = 'https://api.github.com/repos/' . self::GITHUB_REPOSITORY . '/zipball';

        return '' === $tag ? $url : $url . '/' . rawurlencode($tag);
    }

    /**
     * Returns the repository homepage.
     */
    private static function get_repository_url(): string
    {
        return 'https://github.com/' . self::GITHUB_REPOSITORY;
    }

    /**
     * Returns the WordPress plugin basename.
     */
    private static function get_plugin_basename(): string
    {
        return plugin_basename(PLUGIN_APA_AGADEV_FILE);
    }

    /**
     * Reads the installed version from the plugin header.
     */
    private static function get_plugin_version(): string
    {
        if (! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data(PLUGIN_APA_AGADEV_FILE, false, false);

        return isset($plugin_data['Version']) ? (string) $plugin_data['Version'] : '';
    }

    /**
     * Moves the extracted GitHub folder to the expected WordPress directory.
     */
    private static function move_folder(string $from, string $to): bool
    {
        global $wp_filesystem;

        if (! $wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (! $wp_filesystem) {
            return false;
        }

        if (untrailingslashit($from) === untrailingslashit($to)) {
            return true;
        }

        if ($wp_filesystem->exists($to)) {
            $wp_filesystem->delete($to, true);
        }

        return $wp_filesystem->move($from, $to, true);
    }
}
