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
<article class="acl_shortcode_agreement_detail acl_shortcode_article apa-order-detail apa-order-document">
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
    $productFields = [];
    $buyer = is_array($order['buyer'] ?? null) ? $order['buyer'] : [];
    $buyerName = trim((string) ($buyer['firstname'] ?? '') . ' ' . (string) ($buyer['lastname'] ?? ''));
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
    <div class="apa-order-document-parties">
        <section><h4>Acheteur</h4><p><?php echo esc_html($buyerName !== '' ? $buyerName : 'Non renseigné'); ?></p></section>
        <section><h4>Date de création</h4><p><?php echo esc_html($formatDate($order['created_at'] ?? null)); ?></p></section>
    </div>
    <div class="apa-order-document-table-wrap" tabindex="0" role="region" aria-label="Articles commandés">
        <table class="apa-order-document-table">
            <caption>Articles commandés</caption>
            <thead><tr><th scope="col">Produit</th><th scope="col">Variantes</th><th scope="col">Quantité</th><th scope="col">Unité</th></tr></thead>
            <tbody><tr>
                <th scope="row"><?php echo esc_html((string) ($order['product']['name'] ?? 'Non renseigné')); ?></th>
                <td><?php if ($productFields === []) : ?>—<?php else : ?><dl class="apa-order-document-variants">
                    <?php foreach ($productFields as [$label, $value]) : ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($value); ?></dd></div><?php endforeach; ?>
                </dl><?php endif; ?></td>
                <td class="apa-order-document-number"><?php echo esc_html((string) ($order['quantity'] ?? '—')); ?></td>
                <td><?php echo esc_html((string) ($order['unit'] ?? '—')); ?></td>
            </tr></tbody>
        </table>
    </div>
    <div class="apa-order-detail-groups">
        <?php foreach (['Livraison' => $deliveryFields, 'Suivi de la commande' => $trackingFields] as $groupTitle => $fields) : ?>
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
    <?php if (in_array($status, ['completed', 'delivered'], true)) : ?>
        <section class="apa-order-lots" aria-label="Lots composant la commande">
            <h4>Lots composant la commande</h4>
            <?php if (empty($order['allocated_lots'])) : ?>
                <p>Les informations des lots ne sont pas disponibles pour le moment.</p>
            <?php else : ?>
                <ul class="apa-order-lots-grid">
                    <?php foreach ($order['allocated_lots'] as $lot) :
                        $code = trim((string) ($lot['snapshot']['code'] ?? ''));
                        $qrImage = $lot['qr_image'] ?? null;
                    ?>
                        <li class="apa-order-lot">
                            <div><span class="apa-order-lot-label">Code du lot</span>
                                <strong><?php echo esc_html($code !== '' ? $code : 'Code non renseigné'); ?></strong>
                                <p>Quantité affectée : <?php echo esc_html(trim(($lot['quantity'] ?? '—') . ' ' . ($order['unit'] ?? ''))); ?></p>
                            </div>
                            <?php if (is_string($qrImage) && strpos($qrImage, 'data:image/svg+xml;base64,') === 0) : ?>
                                <img width="160" height="160" src="<?php echo esc_attr($qrImage); ?>" alt="<?php echo esc_attr('QR code du lot ' . ($code !== '' ? $code : 'sans code')); ?>">
                            <?php else : ?>
                                <p class="apa-order-lot-unavailable">QR code indisponible</p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php elseif (! empty($order['allocated_lots'])) : ?>
        <h3>Lots affectés</h3><ul>
        <?php foreach ($order['allocated_lots'] as $lot) : ?><li><?php echo esc_html(($lot['snapshot']['code'] ?? $lot['uuid'] ?? '') . ' · ' . ($lot['quantity'] ?? '') . ' ' . ($order['unit'] ?? '')); ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>
