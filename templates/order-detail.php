<?php
 defined('ABSPATH') || exit;
$formatDate = static function ($value): string {
    if (! is_string($value) || trim($value) === '') return 'Non renseignée';
    $timestamp = strtotime($value);
    return $timestamp === false ? 'Non renseignée' : wp_date('d/m/Y', $timestamp);
};
$status = (string) ($order['status'] ?? '');
$statusClass = ['submitted' => 'pending', 'in_progress' => 'pending', 'completed' => 'valid', 'delivered' => 'valid', 'cancelled' => 'cancelled'][$status] ?? 'draft';
?>
<article class="acl_shortcode_agreement_detail acl_shortcode_article apa-order-detail">
    <header class="acl_shortcode_agreement_detail_header acl_shortcode_div">
        <div><span class="acl_shortcode_agreement_detail_eyebrow">Commande</span>
            <h3 class="acl_shortcode_agreement_detail_title acl_shortcode_h3"><?php echo esc_html(trim((string) ($order['code'] ?? '')) ?: 'Commande en brouillon'); ?></h3>
        </div>
        <span class="acl_shortcode_agreements_status acl_shortcode_agreements_status--<?php echo esc_attr($statusClass); ?>"><?php echo esc_html($this->label($status)); ?></span>
    </header>
    <div class="acl_shortcode_agreement_detail_actions acl_shortcode_div">
        <a class="acl_shortcode_agreement_back acl_shortcode_button_button" href="<?php echo esc_url($this->url()); ?>">Retour à mes commandes</a>
        <?php if ($status === 'draft' && in_array('update', $order['allowed_actions'] ?? [], true)) : ?>
            <a class="acl_shortcode_agreement_download acl_shortcode_button_button" data-apa-order-open aria-haspopup="dialog" href="<?php echo esc_url($this->url(['order_uuid' => $order['uuid'], 'order_action' => 'edit'])); ?>">Modifier / soumettre le brouillon</a>
        <?php endif; ?>
    </div>
    <?php
    $productFields = [['Produit', $order['product']['name'] ?? 'Non renseigné']];
    foreach ((array) ($order['meta'] ?? []) as $key => $value) {
        if (is_scalar($value) && (string) $value !== '') {
            $productFields[] = [function_exists('mb_convert_case') ? mb_convert_case((string) $key, MB_CASE_TITLE, 'UTF-8') : ucfirst((string) $key), (string) $value];
        }
    }
    $deliveryFields = [
        ['Quantité souhaitée', trim(($order['quantity'] ?? '') . ' ' . ($order['unit'] ?? ''))],
        ['Quantité affectée', trim(($order['allocated_quantity'] ?? '') . ' ' . ($order['unit'] ?? ''))],
        ['Livraison attendue', $formatDate($order['expected_delivery_date'] ?? null)],
    ];
    $trackingFields = [['Statut', $this->label($status)]];
    foreach (['created_at' => 'Créée le', 'submitted_at' => 'Soumise le', 'completed_at' => 'Complétée le', 'delivered_at' => 'Livrée le', 'cancelled_at' => 'Annulée le'] as $key => $label) {
        if (! empty($order[$key])) $trackingFields[] = [$label, $formatDate($order[$key])];
    }
    if (! empty($order['cancellation_reason'])) $trackingFields[] = ['Motif d’annulation', $order['cancellation_reason']];
    ?>
    <div class="apa-order-detail-groups">
        <?php foreach (['Produit et variantes' => $productFields, 'Quantité et livraison' => $deliveryFields, 'Suivi de la commande' => $trackingFields] as $groupTitle => $fields) : ?>
            <section class="apa-order-detail-group">
                <h4><?php echo esc_html($groupTitle); ?></h4>
                <dl class="acl_shortcode_agreement_meta">
                    <?php foreach ($fields as [$label, $value]) : ?>
                        <div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html((string) $value); ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
    </div>
    <?php if (! empty($order['allocated_lots'])) : ?>
        <h3>Lots affectés</h3><ul>
        <?php foreach ($order['allocated_lots'] as $lot) : ?><li><?php echo esc_html(($lot['snapshot']['code'] ?? $lot['uuid'] ?? '') . ' · ' . ($lot['quantity'] ?? '') . ' ' . ($order['unit'] ?? '')); ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>
