<?php
/**
 * Public detail page for a locally synchronized final lot.
 */

declare(strict_types=1);

use PluginApaAgadev\Service\LotMediaService;
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
    $claim = is_array($payload['claim'] ?? null) ? $payload['claim'] : [];
    $collector = is_array($payload['collector'] ?? null) ? $payload['collector'] : [];
    $claimed_by = is_array($claim['claimed_by'] ?? null) ? $claim['claimed_by'] : [];
    $public_name = static function (array $person): string {
        return implode(' ', array_values(array_filter([
            trim((string) ($person['firstname'] ?? '')),
            trim((string) ($person['lastname'] ?? '')),
        ], static fn (string $part): bool => '' !== $part)));
    };
    $format_public_date = static function (mixed $value): string {
        if (! is_string($value) || '' === trim($value)) {
            return '';
        }

        $timestamp = strtotime($value);
        if (false === $timestamp) {
            return '';
        }

        return wp_date(
            (string) get_option('date_format'),
            $timestamp
        );
    };
    $collector_name = $public_name($collector);
    $claimant_name = $public_name($claimed_by);
    $claimed_at_label = $format_public_date($claim['claimed_at'] ?? null);
    $post_id = get_the_ID();
    $product_image_id = absint(get_post_meta($post_id, LotMediaService::META_PRODUCT_IMAGE, true));
    $zone_image_id = absint(get_post_meta($post_id, LotMediaService::META_ZONE_IMAGE, true));
    $collector_image_id = absint(get_post_meta($post_id, LotMediaService::META_COLLECTOR_IMAGE, true));
    $claimant_image_id = absint(get_post_meta($post_id, LotMediaService::META_CLAIMANT_IMAGE, true));
    $product_name = trim((string) ($product['name'] ?? ''));
    $product_code = trim((string) ($product['code'] ?? ''));
    $product_description = trim((string) ($product['description'] ?? ''));
    $lot_code = trim((string) ($payload['code'] ?? get_the_title()));
    $has_zone = ! empty($zone['province_name'])
        || ! empty($zone['department_name'])
        || ! empty($zone['department_capital_name'])
        || $zone_image_id > 0;
    $has_value_chain = $has_zone || '' !== $collector_name || '' !== $claimant_name || $collector_image_id > 0 || $claimant_image_id > 0;
    $packaging_snapshot = is_array($payload['packaging_info'] ?? null) ? $payload['packaging_info'] : [];
    $packaging_name = trim((string) ($packaging['name'] ?? ''));
    $packaging_quantity = trim((string) ($packaging_snapshot['quantity'] ?? $packaging['quantity'] ?? ''));
    $packaging_unit = trim((string) ($packaging_snapshot['unit'] ?? $packaging['unit'] ?? ''));
    $unit_quantity = trim(implode(' ', array_filter([$packaging_quantity, $packaging_unit])));
    $total_quantity = isset($payload['total_quantity'])
        ? trim(implode(' ', array_filter([(string) $payload['total_quantity'], $packaging_unit])))
        : '';
    ?>
    <main class="acl_shortcode_page acl_shortcode_main">
        <article class="acl_shortcode_product_detail acl_shortcode_lot_detail acl_shortcode_article">
            <?php if ([] === $payload || '' === $status_label) : ?>
                <div class="acl_shortcode_notice acl_shortcode_notice--error acl_shortcode_div" role="alert">
                    <?php esc_html_e('Les données publiques de ce lot sont indisponibles.', 'plugin-apa-agadev'); ?>
                </div>
            <?php else : ?>
                <header class="acl_shortcode_lot_hero acl_shortcode_div">
                    <div class="acl_shortcode_lot_hero_content acl_shortcode_div">
                        <h1 class="acl_shortcode_title acl_shortcode_h1"><?php echo esc_html('' !== $product_name ? $product_name : $lot_code); ?></h1>
                        <p class="acl_shortcode_lot_code"><?php echo esc_html(sprintf(__('Lot #%s', 'plugin-apa-agadev'), $lot_code)); ?></p>
                    </div>
                    <span class="acl_shortcode_lot_status acl_shortcode_lot_status--<?php echo esc_attr($status); ?> acl_shortcode_span">
                        <?php echo esc_html($status_label); ?>
                    </span>
                </header>

                <section class="acl_shortcode_lot_section acl_shortcode_lot_summary acl_shortcode_div">
                    <h2 class="acl_shortcode_lot_section_title acl_shortcode_lot_section_title--lot acl_shortcode_h2"><?php esc_html_e('Informations du lot', 'plugin-apa-agadev'); ?></h2>
                    <div class="acl_shortcode_lot_overview acl_shortcode_div">
                        <dl class="acl_shortcode_lot_fields">
                            <?php if ('' !== $product_name) : ?>
                                <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Nom du produit', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html($product_name); ?></dd></div>
                            <?php endif; ?>
                            <?php if ('' !== $product_code) : ?>
                                <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Code produit', 'plugin-apa-agadev'); ?></dt><dd><code><?php echo esc_html($product_code); ?></code></dd></div>
                            <?php endif; ?>
                            <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Code du lot', 'plugin-apa-agadev'); ?></dt><dd><code><?php echo esc_html($lot_code); ?></code></dd></div>
                            <?php if ('' !== $packaging_name) : ?>
                                <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Conditionnement', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html($packaging_name); ?></dd></div>
                            <?php endif; ?>
                            <?php if ('' !== $unit_quantity) : ?>
                                <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Quantité par unité', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html($unit_quantity); ?></dd></div>
                            <?php endif; ?>
                            <?php if (isset($payload['package_count'])) : ?>
                                <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Nombre d’unités', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html((string) $payload['package_count']); ?></dd></div>
                            <?php endif; ?>
                            <?php if ('' !== $total_quantity) : ?>
                                <div class="acl_shortcode_lot_field acl_shortcode_lot_field--total"><dt><?php esc_html_e('Quantité totale', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html($total_quantity); ?></dd></div>
                            <?php endif; ?>
                        </dl>
                        <div class="acl_shortcode_lot_product acl_shortcode_div">
                            <?php if ($product_image_id > 0) : ?>
                                <figure class="acl_shortcode_lot_product_media">
                                    <?php echo wp_kses_post(wp_get_attachment_image($product_image_id, 'large')); ?>
                                </figure>
                            <?php endif; ?>
                            <?php if ('' !== $product_name) : ?><h2 class="acl_shortcode_h2"><?php echo esc_html($product_name); ?></h2><?php endif; ?>
                            <?php if ('' !== $product_description) : ?><p><?php echo esc_html($product_description); ?></p><?php endif; ?>
                        </div>
                    </div>
                </section>

                <?php if ($has_value_chain) : ?>
                    <section class="acl_shortcode_lot_section acl_shortcode_lot_value_chain acl_shortcode_div">
                        <h2 class="acl_shortcode_lot_section_title acl_shortcode_h2"><?php esc_html_e('Chaîne de valeurs', 'plugin-apa-agadev'); ?></h2>
                        <div class="acl_shortcode_lot_value_grid acl_shortcode_div" tabindex="0">
                            <?php if ($has_zone) : ?>
                                <article class="acl_shortcode_lot_value_card acl_shortcode_div">
                                    <?php if ($zone_image_id > 0) : ?><div class="acl_shortcode_lot_value_media"><?php echo wp_kses_post(wp_get_attachment_image($zone_image_id, 'medium_large')); ?></div><?php endif; ?>
                                    <div class="acl_shortcode_lot_value_content acl_shortcode_div">
                                        <span class="acl_shortcode_lot_value_icon acl_shortcode_lot_value_icon--zone" aria-hidden="true"></span>
                                        <h3 class="acl_shortcode_h3"><?php esc_html_e('Zone de collecte', 'plugin-apa-agadev'); ?></h3>
                                        <?php if (! empty($zone['province_name'])) : ?><p><?php echo esc_html((string) $zone['province_name']); ?></p><?php endif; ?>
                                        <?php if (! empty($zone['department_name'])) : ?><p class="acl_shortcode_lot_value_meta"><?php echo esc_html((string) $zone['department_name']); ?></p><?php endif; ?>
                                        <?php if (! empty($zone['department_capital_name'])) : ?><p class="acl_shortcode_lot_value_meta"><?php echo esc_html((string) $zone['department_capital_name']); ?></p><?php endif; ?>
                                    </div>
                                </article>
                            <?php endif; ?>
                            <?php if ('' !== $collector_name || $collector_image_id > 0) : ?>
                                <article class="acl_shortcode_lot_value_card acl_shortcode_div">
                                    <?php if ($collector_image_id > 0) : ?><div class="acl_shortcode_lot_value_media"><?php echo wp_kses_post(wp_get_attachment_image($collector_image_id, 'medium_large')); ?></div><?php endif; ?>
                                    <div class="acl_shortcode_lot_value_content acl_shortcode_div">
                                        <span class="acl_shortcode_lot_value_icon acl_shortcode_lot_value_icon--collector" aria-hidden="true"></span>
                                        <h3 class="acl_shortcode_h3"><?php esc_html_e('Collecteur', 'plugin-apa-agadev'); ?></h3>
                                        <?php if ('' !== $collector_name) : ?><p><?php echo esc_html($collector_name); ?></p><?php endif; ?>
                                    </div>
                                </article>
                            <?php endif; ?>
                            <?php if ('' !== $claimant_name || '' !== $claimed_at_label || $claimant_image_id > 0) : ?>
                                <article class="acl_shortcode_lot_value_card acl_shortcode_div">
                                    <?php if ($claimant_image_id > 0) : ?><div class="acl_shortcode_lot_value_media"><?php echo wp_kses_post(wp_get_attachment_image($claimant_image_id, 'medium_large')); ?></div><?php endif; ?>
                                    <div class="acl_shortcode_lot_value_content acl_shortcode_div">
                                        <span class="acl_shortcode_lot_value_icon acl_shortcode_lot_value_icon--claimant" aria-hidden="true"></span>
                                        <h3 class="acl_shortcode_h3"><?php esc_html_e('Acheteur', 'plugin-apa-agadev'); ?></h3>
                                        <?php if ('' !== $claimant_name) : ?><p><?php echo esc_html($claimant_name); ?></p><?php endif; ?>
                                        <?php if ('' !== $claimed_at_label) : ?><p class="acl_shortcode_lot_value_meta"><?php echo esc_html(sprintf(__('Réclamé le %s', 'plugin-apa-agadev'), $claimed_at_label)); ?></p><?php endif; ?>
                                    </div>
                                </article>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();
