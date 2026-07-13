<?php
/**
 * Public ready lot list.
 *
 * @var array<int, mixed> $lots
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<main class="acl_shortcode_page acl_shortcode_main">
<section class="acl_shortcode_products acl_shortcode_public_lots acl_shortcode_div">
    <?php if ($lots === []) : ?>
        <p><?php esc_html_e('Aucun lot public disponible.', 'plugin-apa-agadev'); ?></p>
    <?php else : ?>
        <div class="acl_shortcode_grid acl_shortcode_div">
            <?php foreach ($lots as $lot) : ?>
                <?php
                if (! is_array($lot)) {
                    continue;
                }

                $product = is_array($lot['product'] ?? null) ? $lot['product'] : [];
                $zone = is_array($lot['zone'] ?? null) ? $lot['zone'] : [];
                ?>
                <article class="acl_shortcode_card acl_shortcode_article">
                    <h3 class="acl_shortcode_title acl_shortcode_h3"><?php echo esc_html((string) ($lot['code'] ?? __('Lot', 'plugin-apa-agadev'))); ?></h3>
                    <?php if (! empty($product['name'])) : ?>
                        <p><strong><?php esc_html_e('Produit :', 'plugin-apa-agadev'); ?></strong> <?php echo esc_html((string) $product['name']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($lot['total_quantity'])) : ?>
                        <p><strong><?php esc_html_e('Quantité disponible :', 'plugin-apa-agadev'); ?></strong> <?php echo esc_html((string) $lot['total_quantity']); ?></p>
                    <?php endif; ?>
                    <?php if (! empty($zone['province_name'])) : ?>
                        <p><strong><?php esc_html_e('Province :', 'plugin-apa-agadev'); ?></strong> <?php echo esc_html((string) $zone['province_name']); ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
</main>
