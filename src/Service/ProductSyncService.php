<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

/** Keeps the machine catalog locally; product photos remain owned by WordPress. */
final class ProductSyncService
{
    public const POST_TYPE = 'apa_product';
    public const CRON_HOOK = 'apa_agadev_sync_products';
    public const ADMIN_ACTION = 'apa_agadev_sync_products_now';
    public const NONCE_ACTION = 'apa_agadev_sync_products';
    public const OPTION_LAST_SYNC = 'apa_agadev_products_last_sync';
    public const OPTION_LAST_RESULT = 'apa_agadev_products_last_result';
    public const META_ID = '_apa_agadev_product_id';
    public const META_CODE = '_apa_agadev_product_code';
    public const META_PAYLOAD = '_apa_agadev_product_payload';

    public function __construct(private MaivouDataService $data)
    {
    }

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Produits', 'plugin-apa-agadev'),
                'singular_name' => __('Produit', 'plugin-apa-agadev'),
                'edit_item' => __('Photo du produit', 'plugin-apa-agadev'),
                'featured_image' => __('Photo du produit', 'plugin-apa-agadev'),
                'set_featured_image' => __('Choisir la photo du produit', 'plugin-apa-agadev'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => false,
            'rewrite' => false,
            'supports' => ['thumbnail'],
            'register_meta_box_cb' => [$this, 'registerDetails'],
            'menu_icon' => 'dashicons-products',
            'map_meta_cap' => true,
            'capabilities' => ['create_posts' => 'do_not_allow'],
        ]);
        // Enable the native media selector even when the theme limits thumbnails.
        $support = get_theme_support('post-thumbnails');
        if (false === $support) {
            add_theme_support('post-thumbnails', [self::POST_TYPE]);
        } elseif (is_array($support) && isset($support[0]) && is_array($support[0])) {
            add_theme_support('post-thumbnails', array_unique(array_merge($support[0], [self::POST_TYPE])));
        }
    }

    public function registerDetails(): void
    {
        add_meta_box('apa-product-details', __('Informations synchronisées', 'plugin-apa-agadev'),
            [$this, 'renderDetails'], self::POST_TYPE, 'normal');
    }

    public function renderDetails(\WP_Post $post): void
    {
        $raw = get_post_meta($post->ID, self::META_PAYLOAD, true);
        $product = is_string($raw) ? json_decode($raw, true) : [];
        foreach (['name' => 'Nom', 'code' => 'Code', 'description' => 'Description'] as $key => $label) {
            echo '<p><strong>' . esc_html($label) . '</strong><br>' . esc_html((string) ($product[$key] ?? '')) . '</p>';
        }
        echo '<p>' . esc_html__('Ces informations proviennent de Maivou. La photo choisie ici est utilisée sur les lots associés et conservée lors des synchronisations.', 'plugin-apa-agadev') . '</p>';
    }

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

    public function syncProducts(): array
    {
        update_option(self::OPTION_LAST_SYNC, time(), false);
        $response = $this->data->getProducts();
        $result = ['synced' => 0, 'errors' => 0, 'message' => ''];
        $items = $response['data'];
        // The machine endpoint returns a complete, unpaginated JSON list.
        if (! $response['ok'] || ! is_array($items) || array_values($items) !== $items) {
            $result['errors'] = 1;
            $result['message'] = $response['error'] ?: __('Réponse Maivou invalide pour les produits.', 'plugin-apa-agadev');
        } else {
            foreach ($items as $item) {
                if (! is_array($item) || ! $this->saveProduct($item)) {
                    $result['errors']++;
                } else {
                    $result['synced']++;
                }
            }
            $result['message'] = $result['errors'] > 0
                ? __('Synchronisation des produits terminée avec des erreurs.', 'plugin-apa-agadev')
                : __('Synchronisation des produits terminée.', 'plugin-apa-agadev');
        }
        // Missing/inactive products are retained for the historical lots using them.
        update_option(self::OPTION_LAST_RESULT, $result, false);
        return $result;
    }

    private function saveProduct(array $item): bool
    {
        $id = $item['id'] ?? null;
        if ((! is_int($id) && ! is_string($id)) || ! ctype_digit((string) $id) || (int) $id < 1
            || ! is_string($item['sku'] ?? null) || trim($item['sku']) === ''
            || ! is_string($item['name'] ?? null) || trim($item['name']) === ''
            || (isset($item['description']) && ! is_string($item['description']))) {
            return false;
        }
        $product = [
            'name' => $item['name'],
            'code' => trim($item['sku']),
            'description' => $item['description'] ?? '',
        ];
        $json = wp_json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return false;
        }
        $postId = self::findBy(self::META_ID, (string) $id);
        $post = ['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => sanitize_text_field($product['name'])];
        if ($postId > 0) {
            $post['ID'] = $postId;
        }
        // Never send _thumbnail_id: a catalog refresh must preserve the local photo.
        $saved = wp_insert_post(wp_slash($post), true);
        if (is_wp_error($saved) || (int) $saved < 1) {
            return false;
        }
        update_post_meta($saved, self::META_ID, (string) $id);
        update_post_meta($saved, self::META_CODE, wp_slash($product['code']));
        update_post_meta($saved, self::META_PAYLOAD, wp_slash($json));
        return true;
    }

    private static function findBy(string $key, string $value): int
    {
        $ids = get_posts([
            'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'fields' => 'ids',
            'posts_per_page' => 2, 'no_found_rows' => true, 'meta_key' => $key, 'meta_value' => $value,
        ]);
        // An ambiguous SKU must not display another product's data or photo.
        return count($ids) === 1 ? (int) $ids[0] : 0;
    }

    public static function resolveForLot(array $fallback, int $fallbackImage): array
    {
        $code = is_string($fallback['code'] ?? null) ? trim($fallback['code']) : '';
        $id = $code !== '' ? self::findBy(self::META_CODE, $code) : 0;
        $raw = $id > 0 ? get_post_meta($id, self::META_PAYLOAD, true) : '';
        $product = is_string($raw) ? json_decode($raw, true) : null;
        $photo = $id > 0 ? (int) get_post_thumbnail_id($id) : 0;
        return [
            'product' => is_array($product) ? $product : $fallback,
            'image_id' => $photo > 0 && wp_attachment_is_image($photo) ? $photo : $fallbackImage,
        ];
    }

    public function handleManualSync(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'plugin-apa-agadev'));
        }
        check_admin_referer(self::NONCE_ACTION);
        $result = $this->syncProducts();
        wp_safe_redirect(add_query_arg(['apa_product_sync' => $result['errors'] > 0 ? 'error' : 'success'],
            admin_url('options-general.php?page=apa-agadev-documentation')));
        exit;
    }
}
