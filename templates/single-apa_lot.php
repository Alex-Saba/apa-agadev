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

                <?php if ('' !== $collector_name || '' !== $claimant_name || '' !== $claimed_at_label || $has_request_details || [] !== $request_history) : ?>
                    <div class="acl_shortcode_lot_traceability acl_shortcode_div">
                        <h2 class="acl_shortcode_lot_section_title acl_shortcode_h2">
                            <?php esc_html_e('Acteurs et suivi de la demande', 'plugin-apa-agadev'); ?>
                        </h2>

                        <div class="acl_shortcode_lot_context acl_shortcode_div">
                            <?php if ('' !== $collector_name) : ?>
                                <section class="acl_shortcode_lot_context_card acl_shortcode_div">
                                    <h3 class="acl_shortcode_h3"><?php esc_html_e('Collecteur', 'plugin-apa-agadev'); ?></h3>
                                    <p><?php echo esc_html($collector_name); ?></p>
                                </section>
                            <?php endif; ?>

                            <?php if ('' !== $claimant_name || '' !== $claimed_at_label) : ?>
                                <section class="acl_shortcode_lot_context_card acl_shortcode_div">
                                    <h3 class="acl_shortcode_h3"><?php esc_html_e('Réclamation', 'plugin-apa-agadev'); ?></h3>
                                    <?php if ('' !== $claimant_name) : ?>
                                        <p><?php echo esc_html($claimant_name); ?></p>
                                    <?php endif; ?>
                                    <?php if ('' !== $claimed_at_label) : ?>
                                        <p class="acl_shortcode_lot_context_meta">
                                            <?php
                                            printf(
                                                /* translators: %s is the localized claim date. */
                                                esc_html__('Réclamé le %s', 'plugin-apa-agadev'),
                                                esc_html($claimed_at_label)
                                            );
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                </section>
                            <?php endif; ?>
                        </div>

                        <?php if ($has_request_details) : ?>
                            <section class="acl_shortcode_lot_request acl_shortcode_div">
                                <h3 class="acl_shortcode_h3"><?php esc_html_e('Demande associée', 'plugin-apa-agadev'); ?></h3>
                                <dl class="acl_shortcode_lot_request_fields">
                                    <?php if ('' !== $request_name) : ?>
                                        <div>
                                            <dt><?php esc_html_e('Nom', 'plugin-apa-agadev'); ?></dt>
                                            <dd><?php echo esc_html($request_name); ?></dd>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ('' !== $request_uuid) : ?>
                                        <div>
                                            <dt><?php esc_html_e('Identifiant', 'plugin-apa-agadev'); ?></dt>
                                            <dd><code><?php echo esc_html($request_uuid); ?></code></dd>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ('' !== $request_status_label) : ?>
                                        <div>
                                            <dt><?php esc_html_e('Statut', 'plugin-apa-agadev'); ?></dt>
                                            <dd><?php echo esc_html($request_status_label); ?></dd>
                                        </div>
                                    <?php endif; ?>
                                </dl>
                            </section>
                        <?php endif; ?>

                        <?php if ([] !== $request_history) : ?>
                            <section class="acl_shortcode_lot_history acl_shortcode_div">
                                <h3 class="acl_shortcode_h3"><?php esc_html_e('Historique de la demande', 'plugin-apa-agadev'); ?></h3>
                                <ol class="acl_shortcode_lot_timeline">
                                    <?php foreach ($request_history as $history_entry) : ?>
                                        <?php
                                        $from_label = $translate_status($history_entry['from'] ?? '');
                                        $to_label = $translate_status($history_entry['to'] ?? '');
                                        $history_date = $format_public_date($history_entry['at'] ?? null);

                                        if ('' === $from_label && '' === $to_label && '' === $history_date) {
                                            continue;
                                        }
                                        ?>
                                        <li class="acl_shortcode_lot_timeline_item">
                                            <span class="acl_shortcode_lot_timeline_marker" aria-hidden="true"></span>
                                            <div class="acl_shortcode_lot_timeline_content acl_shortcode_div">
                                                <?php if ('' !== $from_label || '' !== $to_label) : ?>
                                                    <p class="acl_shortcode_lot_timeline_transition">
                                                        <?php if ('' !== $from_label) : ?>
                                                            <span><?php echo esc_html($from_label); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ('' !== $from_label && '' !== $to_label) : ?>
                                                            <span aria-hidden="true">→</span>
                                                        <?php endif; ?>
                                                        <?php if ('' !== $to_label) : ?>
                                                            <strong><?php echo esc_html($to_label); ?></strong>
                                                        <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ('' !== $history_date) : ?>
                                                    <p class="acl_shortcode_lot_timeline_date"><?php echo esc_html($history_date); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </section>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </article>
    </main>
    <?php
endwhile;

get_footer();
