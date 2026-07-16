<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

/**
 * Manages WordPress-owned images used by synchronized public lot pages.
 */
final class LotMediaService
{
    public const META_PRODUCT_IMAGE = '_apa_agadev_product_image_id';

    public const META_ZONE_IMAGE = '_apa_agadev_zone_image_id';

    public const META_COLLECTOR_IMAGE = '_apa_agadev_collector_image_id';

    public const META_CLAIMANT_IMAGE = '_apa_agadev_claimant_image_id';

    private const NONCE_ACTION = 'apa_agadev_save_lot_media';

    private const NONCE_FIELD = 'apa_agadev_lot_media_nonce';

    public function registerMetaBox(\WP_Post $post): void
    {
        add_meta_box(
            'apa-agadev-lot-media',
            __('Images de la fiche publique', 'plugin-apa-agadev'),
            [$this, 'renderMetaBox'],
            LotSyncService::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function enqueueAdminAssets(string $hook_suffix): void
    {
        if (! in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen instanceof \WP_Screen || LotSyncService::POST_TYPE !== $screen->post_type) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'plugin-apa-agadev-admin',
            PLUGIN_APA_AGADEV_URL . 'assets/apa-agadev-admin.css',
            [],
            PLUGIN_APA_AGADEV_VERSION
        );
        wp_enqueue_script(
            'plugin-apa-agadev-admin',
            PLUGIN_APA_AGADEV_URL . 'assets/apa-agadev-admin.js',
            [],
            PLUGIN_APA_AGADEV_VERSION,
            true
        );
        wp_localize_script('plugin-apa-agadev-admin', 'apaAgadevLotMedia', [
            'frameTitle' => __('Choisir une image', 'plugin-apa-agadev'),
            'frameButton' => __('Utiliser cette image', 'plugin-apa-agadev'),
            'selectButton' => __('Choisir une image', 'plugin-apa-agadev'),
            'replaceButton' => __('Remplacer', 'plugin-apa-agadev'),
        ]);
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        ?>
        <p>
            <?php esc_html_e('Ces images proviennent de la médiathèque WordPress et ne sont pas remplacées par la synchronisation Maivou.', 'plugin-apa-agadev'); ?>
        </p>
        <div class="apa-agadev-media-grid">
            <?php foreach ($this->fields() as $field => $configuration) : ?>
                <?php
                $attachment_id = absint(get_post_meta($post->ID, $configuration['meta_key'], true));
                $preview = $attachment_id > 0
                    ? wp_get_attachment_image($attachment_id, 'medium', false, ['class' => 'apa-agadev-media-preview-image'])
                    : '';
                ?>
                <div class="apa-agadev-media-field" data-apa-agadev-media-field>
                    <strong><?php echo esc_html($configuration['label']); ?></strong>
                    <div class="apa-agadev-media-preview" data-apa-agadev-media-preview>
                        <?php echo wp_kses_post($preview); ?>
                    </div>
                    <input
                        type="hidden"
                        name="apa_agadev_lot_media[<?php echo esc_attr($field); ?>]"
                        value="<?php echo esc_attr((string) $attachment_id); ?>"
                        data-apa-agadev-media-input
                    >
                    <div class="apa-agadev-media-actions">
                        <button type="button" class="button" data-apa-agadev-media-select>
                            <?php echo esc_html($attachment_id > 0 ? __('Remplacer', 'plugin-apa-agadev') : __('Choisir une image', 'plugin-apa-agadev')); ?>
                        </button>
                        <button
                            type="button"
                            class="button-link-delete<?php echo $attachment_id > 0 ? '' : ' is-hidden'; ?>"
                            data-apa-agadev-media-remove
                        >
                            <?php esc_html_e('Retirer', 'plugin-apa-agadev'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function saveMetaBox(int $post_id, \WP_Post $post): void
    {
        if (LotSyncService::POST_TYPE !== $post->post_type || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $nonce = isset($_POST[self::NONCE_FIELD])
            ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_FIELD]))
            : '';

        if ('' === $nonce || ! wp_verify_nonce($nonce, self::NONCE_ACTION) || ! current_user_can('edit_post', $post_id)) {
            return;
        }

        $submitted = isset($_POST['apa_agadev_lot_media']) && is_array($_POST['apa_agadev_lot_media'])
            ? wp_unslash($_POST['apa_agadev_lot_media'])
            : [];

        foreach ($this->fields() as $field => $configuration) {
            $submitted_id = $submitted[$field] ?? 0;
            $attachment_id = is_scalar($submitted_id) ? absint($submitted_id) : 0;

            if ($attachment_id > 0 && wp_attachment_is_image($attachment_id)) {
                update_post_meta($post_id, $configuration['meta_key'], $attachment_id);
            } else {
                delete_post_meta($post_id, $configuration['meta_key']);
            }
        }
    }

    /**
     * @return array<string, array{meta_key:string,label:string}>
     */
    private function fields(): array
    {
        return [
            'product' => [
                'meta_key' => self::META_PRODUCT_IMAGE,
                'label' => __('Image principale du produit', 'plugin-apa-agadev'),
            ],
            'zone' => [
                'meta_key' => self::META_ZONE_IMAGE,
                'label' => __('Image de la zone de collecte', 'plugin-apa-agadev'),
            ],
            'collector' => [
                'meta_key' => self::META_COLLECTOR_IMAGE,
                'label' => __('Image du collecteur', 'plugin-apa-agadev'),
            ],
            'claimant' => [
                'meta_key' => self::META_CLAIMANT_IMAGE,
                'label' => __('Image du transformateur ou réclamant', 'plugin-apa-agadev'),
            ],
        ];
    }
}
