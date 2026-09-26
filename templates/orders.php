<?php defined('ABSPATH') || exit; ?>
<?php if (isset($detail)) : ?>
<section class="acl_shortcode_agreements acl_shortcode_div apa-orders-list">
    <header class="acl_shortcode_agreements_header acl_shortcode_div"><h2 class="acl_shortcode_agreements_title acl_shortcode_h2">Mes commandes</h2></header>
    <?php echo $detail; // Escaped by order-detail.php. ?>
</section>
<?php return; endif; ?>
<section class="acl_shortcode_agreements acl_shortcode_div apa-orders-list">
    <header class="acl_shortcode_agreements_header acl_shortcode_div"><h2 class="acl_shortcode_agreements_title acl_shortcode_h2">Mes commandes</h2>
        <?php if ($canCreate) : ?><a class="acl_shortcode_agreements_add acl_shortcode_button_button" data-apa-order-open aria-haspopup="dialog" href="<?php echo esc_url($this->url(['order_action' => 'new'])); ?>">Ajouter une commande</a><?php endif; ?>
    </header>
    <?php if ($orders === []) : ?>
        <div class="acl_shortcode_agreements_empty acl_shortcode_div">
            <h3 class="acl_shortcode_h3">Aucune commande pour le moment</h3>
            <p class="acl_shortcode_p">Votre première commande apparaîtra ici après sa création.</p>
        </div>
    <?php else : ?>
        <div class="acl_shortcode_agreements_table_wrap acl_shortcode_div"><table class="acl_shortcode_agreements_table"><thead><tr><th>Commande</th><th>Produit</th><th>Quantité</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($orders as $order) : ?>
            <tr>
                <td><?php echo esc_html(trim((string) ($order['code'] ?? '')) ?: 'Formulaire de commande'); ?></td>
                <td><?php echo esc_html((string) ($order['product']['name'] ?? '')); ?></td>
                <td><?php echo esc_html(trim(($order['quantity'] ?? '') . ' ' . ($order['unit'] ?? ''))); ?></td>
                <td><span class="acl_shortcode_agreements_status acl_shortcode_agreements_status--<?php echo esc_attr(['submitted' => 'pending', 'in_progress' => 'pending', 'completed' => 'valid', 'delivered' => 'valid', 'cancelled' => 'cancelled'][$order['status'] ?? ''] ?? 'draft'); ?>"><?php echo esc_html($this->label((string) ($order['status'] ?? ''))); ?></span></td>
                <td class="acl_shortcode_agreements_actions">
                    <?php if (($order['status'] ?? '') === 'draft' && in_array('update', $order['allowed_actions'] ?? [], true)) : ?>
                        <a class="acl_shortcode_agreements_action" data-apa-order-open aria-haspopup="dialog" href="<?php echo esc_url($this->url(['order_uuid' => $order['uuid'], 'order_action' => 'edit'])); ?>" aria-label="Modifier le brouillon" title="Modifier" data-tooltip="Modifier">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                        </a>
                    <?php endif; ?>
                    <a class="acl_shortcode_agreements_action" href="<?php echo esc_url($this->url(['order_uuid' => $order['uuid']])); ?>" aria-label="Consulter la commande" title="Consulter" data-tooltip="Consulter">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
    <?php if ($lastPage > 1) : ?>
    <nav class="acl_shortcode_agreements_pagination" aria-label="Pagination des commandes">
        <?php if ($page > 1) : ?><a class="acl_shortcode_agreements_page" href="<?php echo esc_url($this->url(['order_page' => $page - 1])); ?>">Précédent</a><?php endif; ?>
        <span><?php echo esc_html('Page ' . $page . ' / ' . max(1, $lastPage)); ?></span>
        <?php if ($page < $lastPage) : ?><a class="acl_shortcode_agreements_page" href="<?php echo esc_url($this->url(['order_page' => $page + 1])); ?>">Suivant</a><?php endif; ?>
    </nav>
    <?php endif; ?>
</section>

<?php if ($modal !== '') : ?>
<div class="acl_shortcode_agreement_modal is-open acl_shortcode_div" data-apa-order-modal data-return-url="<?php echo esc_url($this->url()); ?>">
    <a class="acl_shortcode_agreement_modal_backdrop" href="<?php echo esc_url($this->url()); ?>" tabindex="-1" aria-hidden="true"></a>
    <div class="acl_shortcode_agreement_modal_dialog acl_shortcode_div" role="dialog" aria-modal="true" aria-labelledby="apa-order-modal-title" tabindex="-1">
        <header class="acl_shortcode_agreement_modal_header acl_shortcode_div">
            <div class="acl_shortcode_div"><span class="acl_shortcode_agreement_modal_eyebrow">Commande</span><h2 id="apa-order-modal-title" class="acl_shortcode_agreement_modal_title acl_shortcode_h2"><?php echo esc_html($modalTitle); ?></h2></div>
            <a class="acl_shortcode_agreement_modal_close" href="<?php echo esc_url($this->url()); ?>" aria-label="Fermer">&times;</a>
        </header>
        <div class="acl_shortcode_agreement_modal_body acl_shortcode_div">
            <?php echo $modal; // Escaped by the order templates and error renderer. ?>
        </div>
    </div>
</div>
<?php endif; ?>
