<?php
/**
 * Public detail page for a locally synchronized final lot.
 */

declare(strict_types=1);

use PluginApaAgadev\Service\LotSyncService;

if (! defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $payload_raw = get_post_meta(get_the_ID(), LotSyncService::META_PAYLOAD, true);
    $payload = json_decode(is_string($payload_raw) ? $payload_raw : '', true);
    $payload = is_array($payload) ? $payload : [];
    $status = (string) ($payload['status'] ?? '');
    $status_label = 'sold' === $status
        ? __('Vendu', 'plugin-apa-agadev')
        : ('cancelled' === $status ? __('Annulé', 'plugin-apa-agadev') : '');
    $product = is_array($payload['product'] ?? null) ? $payload['product'] : [];
    $packaging = is_array($payload['packaging'] ?? null) ? $payload['packaging'] : [];
    $zone = is_array($payload['zone'] ?? null) ? $payload['zone'] : [];
    $packaging_parts = array_values(array_filter([
        trim((string) ($packaging['name'] ?? '')),
        trim((string) ($packaging['quantity'] ?? '')),
        trim((string) ($packaging['unit'] ?? '')),
    ], static fn (string $value): bool => '' !== $value));
    ?>
    <main class="acl_shortcode_page acl_shortcode_main">
        <article class="acl_shortcode_product_detail acl_shortcode_lot_detail acl_shortcode_article">
            <?php if ([] === $payload || '' === $status_label) : ?>
                <div class="acl_shortcode_notice acl_shortcode_notice--error acl_shortcode_div" role="alert">
                    <?php esc_html_e('Les données publiques de ce lot sont indisponibles.', 'plugin-apa-agadev'); ?>
                </div>
            <?php else : ?>
                <header class="acl_shortcode_header acl_shortcode_div">
                    <div class="acl_shortcode_eyebrow acl_shortcode_div"><?php esc_html_e('Traçabilité du lot', 'plugin-apa-agadev'); ?></div>
                    <h1 class="acl_shortcode_title acl_shortcode_h1"><?php echo esc_html((string) ($payload['code'] ?? get_the_title())); ?></h1>
                    <span class="acl_shortcode_lot_status acl_shortcode_lot_status--<?php echo esc_attr($status); ?> acl_shortcode_span">
                        <?php echo esc_html($status_label); ?>
                    </span>
                </header>

                <div class="acl_shortcode_lot_fields acl_shortcode_div">
                    <?php if (! empty($product['name'])) : ?>
                        <div class="acl_shortcode_lot_field acl_shortcode_div">
                            <strong><?php esc_html_e('Produit', 'plugin-apa-agadev'); ?></strong>
                            <span><?php echo esc_html((string) $product['name']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($payload['total_quantity'])) : ?>
                        <div class="acl_shortcode_lot_field acl_shortcode_div">
                            <strong><?php esc_html_e('Quantité', 'plugin-apa-agadev'); ?></strong>
                            <span><?php echo esc_html((string) $payload['total_quantity']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ([] !== $packaging_parts) : ?>
                        <div class="acl_shortcode_lot_field acl_shortcode_div">
                            <strong><?php esc_html_e('Conditionnement', 'plugin-apa-agadev'); ?></strong>
                            <span><?php echo esc_html(implode(' · ', $packaging_parts)); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (! empty($zone['province_name'])) : ?>
                        <div class="acl_shortcode_lot_field acl_shortcode_div">
                            <strong><?php esc_html_e('Province', 'plugin-apa-agadev'); ?></strong>
                            <span><?php echo esc_html((string) $zone['province_name']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (! empty($zone['department_name'])) : ?>
                        <div class="acl_shortcode_lot_field acl_shortcode_div">
                            <strong><?php esc_html_e('Département', 'plugin-apa-agadev'); ?></strong>
                            <span><?php echo esc_html((string) $zone['department_name']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (! empty($payload['signature'])) : ?>
                    <div class="acl_shortcode_lot_signature acl_shortcode_div">
                        <strong><?php esc_html_e('Signature', 'plugin-apa-agadev'); ?></strong>
                        <code><?php echo esc_html((string) $payload['signature']); ?></code>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();
