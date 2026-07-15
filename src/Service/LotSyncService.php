<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

/**
 * Synchronizes final Maivou lots into local, individually addressable posts.
 */
final class LotSyncService
{
    public const POST_TYPE = 'apa_lot';

    public const CRON_HOOK = 'apa_agadev_sync_lots';

    public const ADMIN_ACTION = 'apa_agadev_sync_lots_now';

    public const NONCE_ACTION = 'apa_agadev_sync_lots';

    public const OPTION_LAST_SYNC = 'apa_agadev_lots_last_sync';

    public const OPTION_LAST_RESULT = 'apa_agadev_lots_last_result';

    public const META_LOT_UUID = '_apa_agadev_lot_uuid';

    public const META_LOT_CODE = '_apa_agadev_lot_code';

    public const META_REQUEST_UUID = '_apa_agadev_request_uuid';

    public const META_PAYLOAD = '_apa_agadev_lot_payload';

    private const LOTS_PER_PAGE = 100;

    private MaivouDataService $data;

    public function __construct(MaivouDataService $data)
    {
        $this->data = $data;
    }

    /**
     * Registers final lots as public single pages without an archive or search listing.
     */
    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Lots APA', 'plugin-apa-agadev'),
                'singular_name' => __('Lot APA', 'plugin-apa-agadev'),
                'edit_item' => __('Consulter le lot APA', 'plugin-apa-agadev'),
                'view_item' => __('Voir le lot APA', 'plugin-apa-agadev'),
                'search_items' => __('Rechercher un lot APA', 'plugin-apa-agadev'),
                'not_found' => __('Aucun lot APA synchronisé.', 'plugin-apa-agadev'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => [
                'slug' => 'lot',
                'with_front' => false,
            ],
            'query_var' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-archive',
            'map_meta_cap' => true,
            'capabilities' => [
                // Lots are owned by the synchronization and cannot be created manually.
                'create_posts' => 'do_not_allow',
            ],
        ]);
    }

    /**
     * Recognizes the historical root URL containing only a request UUID.
     */
    public function registerRewriteRules(): void
    {
        add_rewrite_rule(
            '^([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})/?$',
            'index.php?apa_agadev_request_uuid=$matches[1]',
            'top'
        );
    }

    /**
     * Adds the internal request UUID resolver to WordPress' public query vars.
     *
     * @param array<int, string> $query_vars
     * @return array<int, string>
     */
    public function registerQueryVar(array $query_vars): array
    {
        $query_vars[] = 'apa_agadev_request_uuid';

        return $query_vars;
    }

    /**
     * Redirects an existing QR request UUID to the canonical /lot/{code}/ URL.
     */
    public function redirectLegacyRequestUuid(): void
    {
        $request_uuid = (string) get_query_var('apa_agadev_request_uuid', '');

        if ('' === $request_uuid) {
            return;
        }

        $post_id = $this->findPostIdByMeta(self::META_REQUEST_UUID, $request_uuid);

        if ($post_id > 0) {
            wp_safe_redirect((string) get_permalink($post_id), 301, 'APA Agadev');
            exit;
        }

        // A recognized QR that has not been synchronized must remain an explicit 404.
        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }
        status_header(404);
        nocache_headers();
    }

    /**
     * Uses the plugin-owned template only for synchronized lot pages.
     */
    public function filterSingleTemplate(string $template): string
    {
        if (! is_singular(self::POST_TYPE)) {
            return $template;
        }

        $custom_template = PLUGIN_APA_AGADEV_PATH . 'templates/single-apa_lot.php';

        return is_readable($custom_template) ? $custom_template : $template;
    }

    /**
     * Loads APA-specific layout after the shared Bridge stylesheet.
     */
    public function enqueueSingleAssets(): void
    {
        if (is_singular(self::POST_TYPE)) {
            wp_enqueue_style('plugin-apa-agadev');
        }
    }

    /**
     * Ensures the hourly synchronization exists after activation and updates.
     */
    public function ensureScheduled(): void
    {
        self::schedule();
    }

    public static function schedule(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'hourly', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Synchronizes every machine page without deleting local posts on failure.
     *
     * @return array{synced:int,errors:int,pages:int,message:string}
     */
    public function syncLots(): array
    {
        update_option(self::OPTION_LAST_SYNC, time(), false);

        $synced = 0;
        $errors = 0;
        $pages = 0;
        $message = '';
        $page = 1;

        do {
            $response = $this->data->getFinalLotsPage($page, self::LOTS_PER_PAGE);

            if (! $response['ok']) {
                $errors++;
                $message = trim((string) ($response['error'] ?? ''));
                if ('' === $message) {
                    $message = __('Impossible de récupérer les lots finalisés depuis Maivou.', 'plugin-apa-agadev');
                }
                break;
            }

            $payload = $response['data'];

            if (
                ! is_array($payload)
                || ! is_array($payload['data'] ?? null)
                || ! isset($payload['current_page'], $payload['last_page'])
            ) {
                $errors++;
                $message = __('La réponse paginée de Maivou pour les lots est invalide.', 'plugin-apa-agadev');
                break;
            }

            $current_page = (int) $payload['current_page'];
            $last_page = (int) $payload['last_page'];

            if ($current_page !== $page || $current_page < 1 || $last_page < $current_page) {
                $errors++;
                $message = __('La pagination Maivou pour les lots est incohérente.', 'plugin-apa-agadev');
                break;
            }

            foreach ($payload['data'] as $lot) {
                if (! is_array($lot) || ! $this->isFinalLotPayload($lot)) {
                    $errors++;
                    continue;
                }

                if ($this->upsertLot($lot)) {
                    $synced++;
                } else {
                    $errors++;
                }
            }

            $pages++;
            $page = $current_page + 1;
        } while ($current_page < $last_page);

        if ('' === $message) {
            $message = 0 === $errors
                ? __('Synchronisation des lots terminée.', 'plugin-apa-agadev')
                : __('Synchronisation terminée avec des erreurs.', 'plugin-apa-agadev');
        }

        $result = [
            'synced' => $synced,
            'errors' => $errors,
            'pages' => $pages,
            'message' => $message,
        ];

        update_option(self::OPTION_LAST_RESULT, $result, false);

        return $result;
    }

    /**
     * Runs the same synchronization from the protected administration action.
     */
    public function handleManualSync(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'plugin-apa-agadev'));
        }

        check_admin_referer(self::NONCE_ACTION);
        $result = $this->syncLots();
        $state = ($result['errors'] ?? 0) > 0 ? 'error' : 'success';
        $url = add_query_arg(
            'apa_lot_sync',
            $state,
            admin_url('options-general.php?page=apa-agadev-documentation')
        );

        wp_safe_redirect($url);
        exit;
    }

    /**
     * Accepts only the final contract and requires the request association used by QR redirects.
     *
     * @param array<string, mixed> $lot
     */
    private function isFinalLotPayload(array $lot): bool
    {
        $status = (string) ($lot['status'] ?? '');
        $uuid = (string) ($lot['uuid'] ?? '');
        $code = trim((string) ($lot['code'] ?? ''));
        $request = is_array($lot['request'] ?? null) ? $lot['request'] : [];
        $request_uuid = (string) ($request['uuid'] ?? '');

        return in_array($status, ['sold', 'cancelled'], true)
            && '' !== $code
            && wp_is_uuid($uuid)
            && wp_is_uuid($request_uuid);
    }

    /**
     * @param array<string, mixed> $lot
     */
    private function upsertLot(array $lot): bool
    {
        $uuid = sanitize_text_field((string) $lot['uuid']);
        $code = sanitize_text_field((string) $lot['code']);
        $request = is_array($lot['request'] ?? null) ? $lot['request'] : [];
        $request_uuid = sanitize_text_field((string) ($request['uuid'] ?? ''));
        $payload_json = wp_json_encode($lot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($payload_json)) {
            return false;
        }

        $post_id = $this->findPostIdByMeta(self::META_LOT_UUID, $uuid);
        if ($post_id <= 0) {
            $post_id = $this->findPostIdByMeta(self::META_LOT_CODE, $code);
        }

        $post_data = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $code,
            'post_name' => sanitize_title($code),
        ];

        if ($post_id > 0) {
            $post_data['ID'] = $post_id;
            $saved_post_id = wp_update_post($post_data, true);
        } else {
            $saved_post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($saved_post_id) || (int) $saved_post_id <= 0) {
            return false;
        }

        $saved_post_id = (int) $saved_post_id;
        update_post_meta($saved_post_id, self::META_LOT_UUID, $uuid);
        update_post_meta($saved_post_id, self::META_LOT_CODE, $code);
        update_post_meta($saved_post_id, self::META_REQUEST_UUID, $request_uuid);
        update_post_meta($saved_post_id, self::META_PAYLOAD, wp_slash($payload_json));

        return true;
    }

    private function findPostIdByMeta(string $meta_key, string $meta_value): int
    {
        if ('' === $meta_value) {
            return 0;
        }

        $post_ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_key' => $meta_key,
            'meta_value' => $meta_value,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        return isset($post_ids[0]) ? (int) $post_ids[0] : 0;
    }
}
