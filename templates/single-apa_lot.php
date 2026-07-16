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
    $request = is_array($payload['request'] ?? null) ? $payload['request'] : [];
    $claim = is_array($payload['claim'] ?? null) ? $payload['claim'] : [];
    $collector = is_array($payload['collector'] ?? null) ? $payload['collector'] : [];
    $claimed_by = is_array($claim['claimed_by'] ?? null) ? $claim['claimed_by'] : [];
    $request_history = is_array($request['status_history'] ?? null)
        ? array_values(array_filter(
            $request['status_history'],
            static fn (mixed $entry): bool => is_array($entry)
                && (
                    '' !== trim((string) ($entry['from'] ?? ''))
                    || '' !== trim((string) ($entry['to'] ?? ''))
                    || '' !== trim((string) ($entry['at'] ?? ''))
                )
        ))
        : [];
    $status_labels = [
        'ar_lot' => __('Réservation du lot', 'plugin-apa-agadev'),
        'transformation' => __('Transformation', 'plugin-apa-agadev'),
        'conditionnement' => __('Conditionnement', 'plugin-apa-agadev'),
        'facturation' => __('Facturation', 'plugin-apa-agadev'),
        'vendu' => __('Vendu', 'plugin-apa-agadev'),
        'annule' => __('Annulé', 'plugin-apa-agadev'),
        'sold' => __('Vendu', 'plugin-apa-agadev'),
        'cancelled' => __('Annulé', 'plugin-apa-agadev'),
    ];
    $translate_status = static function (mixed $value) use ($status_labels): string {
        $status_value = trim((string) $value);

        return $status_labels[$status_value] ?? $status_value;
    };
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
            (string) get_option('date_format') . ' ' . (string) get_option('time_format'),
            $timestamp
        );
    };
    // The API contract is chronological; sorting also protects older cached payloads.
    usort($request_history, static function (array $left, array $right): int {
        $left_timestamp = strtotime((string) ($left['at'] ?? '')) ?: 0;
        $right_timestamp = strtotime((string) ($right['at'] ?? '')) ?: 0;

        return $left_timestamp <=> $right_timestamp;
    });
    $collector_name = $public_name($collector);
    $claimant_name = $public_name($claimed_by);
    $claimed_at_label = $format_public_date($claim['claimed_at'] ?? null);
    $request_name = trim((string) ($request['name'] ?? ''));
    $request_uuid = trim((string) ($request['uuid'] ?? ''));
    $request_status_label = $translate_status($request['status'] ?? '');
    $has_request_details = '' !== $request_name || '' !== $request_uuid || '' !== $request_status_label;
    $post_id = get_the_ID();
    $product_image_id = absint(get_post_meta($post_id, LotMediaService::META_PRODUCT_IMAGE, true));
    $zone_image_id = absint(get_post_meta($post_id, LotMediaService::META_ZONE_IMAGE, true));
    $collector_image_id = absint(get_post_meta($post_id, LotMediaService::META_COLLECTOR_IMAGE, true));
    $claimant_image_id = absint(get_post_meta($post_id, LotMediaService::META_CLAIMANT_IMAGE, true));
    $product_name = trim((string) ($product['name'] ?? ''));
    $lot_code = trim((string) ($payload['code'] ?? get_the_title()));
    $has_zone = ! empty($zone['province_name']) || ! empty($zone['department_name']) || $zone_image_id > 0;
    $has_value_chain = $has_zone || '' !== $collector_name || '' !== $claimant_name || $collector_image_id > 0 || $claimant_image_id > 0;
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
                <header class="acl_shortcode_lot_hero acl_shortcode_div">
                    <div class="acl_shortcode_lot_hero_content acl_shortcode_div">
                        <div class="acl_shortcode_eyebrow acl_shortcode_div"><?php esc_html_e('Traçabilité du lot', 'plugin-apa-agadev'); ?></div>
                        <h1 class="acl_shortcode_title acl_shortcode_h1"><?php echo esc_html('' !== $product_name ? $product_name : $lot_code); ?></h1>
                        <p class="acl_shortcode_lot_code"><?php echo esc_html(sprintf(__('Lot #%s', 'plugin-apa-agadev'), $lot_code)); ?></p>
                        <span class="acl_shortcode_lot_status acl_shortcode_lot_status--<?php echo esc_attr($status); ?> acl_shortcode_span">
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </div>
                    <?php if ($product_image_id > 0) : ?>
                        <figure class="acl_shortcode_lot_hero_media">
                            <?php echo wp_kses_post(wp_get_attachment_image($product_image_id, 'large')); ?>
                        </figure>
                    <?php endif; ?>
                </header>

                <section class="acl_shortcode_lot_section acl_shortcode_lot_summary acl_shortcode_div">
                    <h2 class="acl_shortcode_lot_section_title acl_shortcode_h2"><?php esc_html_e('Informations du lot', 'plugin-apa-agadev'); ?></h2>
                    <dl class="acl_shortcode_lot_fields">
                        <?php if ('' !== $product_name) : ?>
                            <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Nom du produit', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html($product_name); ?></dd></div>
                        <?php endif; ?>
                        <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Code', 'plugin-apa-agadev'); ?></dt><dd><code><?php echo esc_html($lot_code); ?></code></dd></div>
                        <?php if ([] !== $packaging_parts) : ?>
                            <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Conditionnement', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html(implode(' · ', $packaging_parts)); ?></dd></div>
                        <?php endif; ?>
                        <?php if (isset($payload['package_count'])) : ?>
                            <div class="acl_shortcode_lot_field"><dt><?php esc_html_e('Nombre d’unités', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html((string) $payload['package_count']); ?></dd></div>
                        <?php endif; ?>
                        <?php if (isset($payload['total_quantity'])) : ?>
                            <div class="acl_shortcode_lot_field acl_shortcode_lot_field--total"><dt><?php esc_html_e('Quantité totale', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html((string) $payload['total_quantity']); ?></dd></div>
                        <?php endif; ?>
                    </dl>
                </section>

                <?php if ($has_value_chain) : ?>
                    <section class="acl_shortcode_lot_section acl_shortcode_lot_value_chain acl_shortcode_div">
                        <h2 class="acl_shortcode_lot_section_title acl_shortcode_h2"><?php esc_html_e('Chaîne de valeurs', 'plugin-apa-agadev'); ?></h2>
                        <div class="acl_shortcode_lot_value_grid acl_shortcode_div">
                            <?php if ($has_zone) : ?>
                                <article class="acl_shortcode_lot_value_card acl_shortcode_div">
                                    <?php if ($zone_image_id > 0) : ?><div class="acl_shortcode_lot_value_media"><?php echo wp_kses_post(wp_get_attachment_image($zone_image_id, 'medium_large')); ?></div><?php endif; ?>
                                    <div class="acl_shortcode_lot_value_content acl_shortcode_div">
                                        <span class="acl_shortcode_lot_value_icon" aria-hidden="true"></span>
                                        <h3 class="acl_shortcode_h3"><?php esc_html_e('Zone de collecte', 'plugin-apa-agadev'); ?></h3>
                                        <?php if (! empty($zone['province_name'])) : ?><p><?php echo esc_html((string) $zone['province_name']); ?></p><?php endif; ?>
                                        <?php if (! empty($zone['department_name'])) : ?><p class="acl_shortcode_lot_value_meta"><?php echo esc_html((string) $zone['department_name']); ?></p><?php endif; ?>
                                    </div>
                                </article>
                            <?php endif; ?>
                            <?php if ('' !== $collector_name || $collector_image_id > 0) : ?>
                                <article class="acl_shortcode_lot_value_card acl_shortcode_div">
                                    <?php if ($collector_image_id > 0) : ?><div class="acl_shortcode_lot_value_media"><?php echo wp_kses_post(wp_get_attachment_image($collector_image_id, 'medium_large')); ?></div><?php endif; ?>
                                    <div class="acl_shortcode_lot_value_content acl_shortcode_div">
                                        <span class="acl_shortcode_lot_value_icon" aria-hidden="true"></span>
                                        <h3 class="acl_shortcode_h3"><?php esc_html_e('Collecteur', 'plugin-apa-agadev'); ?></h3>
                                        <?php if ('' !== $collector_name) : ?><p><?php echo esc_html($collector_name); ?></p><?php endif; ?>
                                    </div>
                                </article>
                            <?php endif; ?>
                            <?php if ('' !== $claimant_name || '' !== $claimed_at_label || $claimant_image_id > 0) : ?>
                                <article class="acl_shortcode_lot_value_card acl_shortcode_div">
                                    <?php if ($claimant_image_id > 0) : ?><div class="acl_shortcode_lot_value_media"><?php echo wp_kses_post(wp_get_attachment_image($claimant_image_id, 'medium_large')); ?></div><?php endif; ?>
                                    <div class="acl_shortcode_lot_value_content acl_shortcode_div">
                                        <span class="acl_shortcode_lot_value_icon" aria-hidden="true"></span>
                                        <h3 class="acl_shortcode_h3"><?php esc_html_e('Transformateur / Réclamant', 'plugin-apa-agadev'); ?></h3>
                                        <?php if ('' !== $claimant_name) : ?><p><?php echo esc_html($claimant_name); ?></p><?php endif; ?>
                                        <?php if ('' !== $claimed_at_label) : ?><p class="acl_shortcode_lot_value_meta"><?php echo esc_html(sprintf(__('Réclamé le %s', 'plugin-apa-agadev'), $claimed_at_label)); ?></p><?php endif; ?>
                                    </div>
                                </article>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($has_request_details || [] !== $request_history) : ?>
                    <section class="acl_shortcode_lot_section acl_shortcode_lot_tracking acl_shortcode_div">
                        <h2 class="acl_shortcode_lot_section_title acl_shortcode_h2"><?php esc_html_e('Suivi de la demande', 'plugin-apa-agadev'); ?></h2>
                        <?php if ($has_request_details) : ?>
                            <div class="acl_shortcode_lot_request acl_shortcode_div">
                                <dl class="acl_shortcode_lot_request_fields">
                                    <?php if ('' !== $request_name) : ?><div><dt><?php esc_html_e('Demande', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html($request_name); ?></dd></div><?php endif; ?>
                                    <?php if ('' !== $request_uuid) : ?><div><dt><?php esc_html_e('Identifiant', 'plugin-apa-agadev'); ?></dt><dd><code><?php echo esc_html($request_uuid); ?></code></dd></div><?php endif; ?>
                                    <?php if ('' !== $request_status_label) : ?><div><dt><?php esc_html_e('Statut', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html($request_status_label); ?></dd></div><?php endif; ?>
                                </dl>
                            </div>
                        <?php endif; ?>
                        <?php if ([] !== $request_history) : ?>
                            <div class="acl_shortcode_lot_history acl_shortcode_div">
                                <h3 class="acl_shortcode_h3"><?php esc_html_e('Historique', 'plugin-apa-agadev'); ?></h3>
                                <ol class="acl_shortcode_lot_timeline">
                                    <?php foreach ($request_history as $history_entry) : ?>
                                        <?php
                                        $from_label = $translate_status($history_entry['from'] ?? '');
                                        $to_label = $translate_status($history_entry['to'] ?? '');
                                        $history_date = $format_public_date($history_entry['at'] ?? null);
                                        ?>
                                        <li class="acl_shortcode_lot_timeline_item">
                                            <span class="acl_shortcode_lot_timeline_marker" aria-hidden="true"></span>
                                            <div class="acl_shortcode_lot_timeline_content">
                                                <?php if ('' !== $from_label || '' !== $to_label) : ?><p class="acl_shortcode_lot_timeline_transition"><span><?php echo esc_html($from_label); ?></span><?php if ('' !== $from_label && '' !== $to_label) : ?><span aria-hidden="true">→</span><?php endif; ?><strong><?php echo esc_html($to_label); ?></strong></p><?php endif; ?>
                                                <?php if ('' !== $history_date) : ?><p class="acl_shortcode_lot_timeline_date"><?php echo esc_html($history_date); ?></p><?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if (! empty($payload['signature'])) : ?>
                    <div class="acl_shortcode_lot_signature acl_shortcode_div"><strong><?php esc_html_e('Signature du lot', 'plugin-apa-agadev'); ?></strong><code><?php echo esc_html((string) $payload['signature']); ?></code></div>
                <?php endif; ?>
            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();
