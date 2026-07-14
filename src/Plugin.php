<?php

declare(strict_types=1);

namespace PluginApaAgadev;

use PluginApaAgadev\Service\AdminDocumentationService;
use PluginApaAgadev\Service\MaivouDataService;
use PluginApaAgadev\Service\ShortcodeService;

/**
 * Main plugin composition root.
 */
final class Plugin
{
    private ShortcodeService $shortcodes;

    private AdminDocumentationService $adminDocumentation;

    public function __construct(
        ?ShortcodeService $shortcodes = null,
        ?AdminDocumentationService $adminDocumentation = null
    )
    {
        $this->shortcodes = $shortcodes ?? new ShortcodeService(new MaivouDataService());
        $this->adminDocumentation = $adminDocumentation ?? new AdminDocumentationService();
    }

    /**
     * Registers WordPress hooks owned by APA Agadev.
     */
    public function register(): void
    {
        $this->shortcodes->register();
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_action('admin_menu', [$this->adminDocumentation, 'registerAdminMenu']);

        if (! function_exists('acl_flows_api_call')) {
            add_action('admin_notices', [$this, 'renderMissingBridgeNotice']);
        }
    }

    /**
     * Registers the stylesheet without loading it until a shortcode is rendered.
     */
    public function registerAssets(): void
    {
        wp_register_style(
            'plugin-apa-agadev',
            PLUGIN_APA_AGADEV_URL . 'assets/apa-agadev.css',
            ['acl-shortcodes'],
            PLUGIN_APA_AGADEV_VERSION
        );

        wp_register_script(
            'plugin-apa-agadev',
            PLUGIN_APA_AGADEV_URL . 'assets/apa-agadev.js',
            [],
            PLUGIN_APA_AGADEV_VERSION,
            true
        );
    }

    /**
     * Makes the missing required plugin explicit in the WordPress administration.
     */
    public function renderMissingBridgeNotice(): void
    {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('APA Agadev nécessite ACL WordPress API Bridge pour accéder aux données Maivou.', 'plugin-apa-agadev');
        echo '</p></div>';
    }
}
