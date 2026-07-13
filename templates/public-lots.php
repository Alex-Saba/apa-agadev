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

<div class="apa-agadev apa-agadev--public-lots">
    <?php if ($lots === []) : ?>
        <p><?php esc_html_e('Aucun lot public disponible.', 'plugin-apa-agadev'); ?></p>
    <?php else : ?>
        <div class="apa-agadev__grid">
            <?php foreach ($lots as $lot) : ?>
                <?php
                if (! is_array($lot)) {
                    continue;
                }

                $product = is_array($lot['product'] ?? null) ? $lot['product'] : [];
                $zone = is_array($lot['zone'] ?? null) ? $lot['zone'] : [];
                ?>
                <article class="apa-agadev__card">
                    <h3><?php echo esc_html((string) ($lot['code'] ?? __('Lot', 'plugin-apa-agadev'))); ?></h3>
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
</div>
