<?php
 defined('ABSPATH') || exit;
$formatDate = static function ($value): string {
    $time = is_string($value) && trim($value) !== '' ? strtotime($value) : false;
    return $time === false ? 'Non renseignée' : wp_date('d/m/Y', $time);
};
$buyer = is_array($order['buyer'] ?? null) ? $order['buyer'] : [];
$name = trim(($buyer['firstname'] ?? '') . ' ' . ($buyer['lastname'] ?? '')) ?: 'Non renseigné';
$quantity = trim(($order['quantity'] ?? '—') . ' ' . ($order['unit'] ?? ''));
$delivery = [
    ['Quantité souhaitée', $quantity],
    ['Quantité affectée', trim(($order['allocated_quantity'] ?? '—') . ' ' . ($order['unit'] ?? ''))],
    ['Livraison attendue', $formatDate($order['expected_delivery_date'] ?? null)],
];
$tracking = [['Statut', 'Soumise'], ['Créée le', $formatDate($order['created_at'] ?? null)], ['Soumise le', $formatDate($order['submitted_at'] ?? null)]];
?>
<!doctype html>
<html lang="fr"><head><meta charset="UTF-8"><title>Confirmation de commande</title>
<style>
@page { margin: 24pt; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #26332d; line-height: 1.45; margin: 0; }
.national-header { margin-bottom: 10pt; table-layout: auto; }
.flag-rule { width: 100pt; }
.flag-rule td { height: 3pt; padding: 0; width: 33.33%; }
.flag-green { background: #07964a; }
.flag-yellow { background: #f6ce26; }
.flag-blue { background: #3874c6; }
.national-motto { color: #657069; font-size: 6.5pt; text-align: right; text-transform: uppercase; }
.institutional-header { margin-bottom: 18pt; table-layout: auto; }
.header-logo { width: 62pt; padding-right: 10pt; vertical-align: middle; }
.header-logo img { width: 55pt; height: auto; }
.header-main { vertical-align: middle; }
.institution-name { color: #124b31; font-size: 9pt; line-height: 1.25; text-transform: uppercase; }
.platform-name { color: #68746d; font-size: 7pt; margin-top: 4pt; text-transform: uppercase; }
.header-mark { width: 22%; color: #657069; font-size: 7pt; font-weight: bold; text-align: right; vertical-align: middle; text-transform: uppercase; }
.document { border: 1px solid #d5e3dc; border-top: 3px solid #11663a; border-radius: 5pt; padding: 25pt; }
.header { border-bottom: 1px solid #d5e3dc; padding-bottom: 18pt; margin-bottom: 24pt; }
.eyebrow { color: #eb6900; margin: 0 0 3pt; }
h1 { margin: 0; font-size: 12pt; color: #143e2a; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
td, th { vertical-align: top; text-align: left; overflow-wrap: break-word; }
.parties { margin-bottom: 24pt; }
.parties td { width: 50%; }
.label { color: #5b6a62; font-weight: bold; margin-bottom: 3pt; }
strong { color: #183126; }
h2 { font-size: 10pt; margin: 0 0 9pt; }
.items { margin-bottom: 22pt; }
.items th { background: #edf5f0; color: #183126; padding: 10pt; border-bottom: 1px solid #d5e3dc; }
.items td { padding: 11pt 10pt 20pt; border-bottom: 1px solid #d5e3dc; }
.number { text-align: right; }
.variant { margin-bottom: 3pt; }
.cards > tbody > tr > td { width: 48%; padding: 0; }
.cards > tbody > tr > td.gap { width: 4%; }
.card { border: 1px solid #d5e3dc; border-radius: 9pt; overflow: hidden; }
.card h2 { background: #edf5f0; color: #183126; padding: 11pt; border-bottom: 1px solid #d5e3dc; margin: 0; }
.fields { padding: 0 11pt; }
.field { padding: 11pt 0; border-bottom: 1px solid #e8eeea; }
.field.last { border-bottom: 0; }
.field strong { display: block; margin-bottom: 4pt; }
</style></head><body>
<table class="national-header"><tr>
    <td><table class="flag-rule"><tr><td class="flag-green"></td><td class="flag-yellow"></td><td class="flag-blue"></td></tr></table></td>
    <td class="national-motto">République Gabonaise · Union · Travail · Justice</td>
</tr></table>
<table class="institutional-header"><tr>
    <td class="header-logo"><img src="<?php echo esc_attr($brand_logo_data_uri); ?>" alt="Logo AGADEV"></td>
    <td class="header-main"><div class="institution-name">Agence Gabonaise pour le Développement de l’Économie Verte</div><div class="platform-name">Plateforme Maivou</div></td>
    <td class="header-mark">Confirmation de commande</td>
</tr></table>
<div class="document">
    <div class="header"><p class="eyebrow">Commande</p><h1><?php echo esc_html(trim((string) ($order['code'] ?? '')) ?: 'Formulaire de commande'); ?></h1></div>
    <table class="parties"><tr>
        <td><div class="label">Acheteur</div><strong><?php echo esc_html($name); ?></strong></td>
        <td><div class="label">Date de création</div><strong><?php echo esc_html($formatDate($order['created_at'] ?? null)); ?></strong></td>
    </tr></table>
    <h2>Articles commandés</h2>
    <table class="items"><thead><tr><th style="width:33%">Produit</th><th style="width:31%">Variantes</th><th style="width:21%" class="number">Quantité</th><th style="width:15%">Unité</th></tr></thead>
    <tbody><tr>
        <td><strong><?php echo esc_html((string) ($order['product']['name'] ?? 'Non renseigné')); ?></strong></td>
        <td><?php $hasVariants = false; foreach ((array) ($order['meta'] ?? []) as $key => $value) :
            if (! is_scalar($value) || (string) $value === '') continue;
            $hasVariants = true;
            $label = function_exists('mb_convert_case') ? mb_convert_case((string) $key, MB_CASE_TITLE, 'UTF-8') : ucfirst((string) $key);
        ?><div class="variant"><strong><?php echo esc_html($label); ?></strong> <?php echo esc_html((string) $value); ?></div><?php endforeach; ?><?php if (! $hasVariants) echo '—'; ?></td>
        <td class="number"><?php echo esc_html((string) ($order['quantity'] ?? '—')); ?></td>
        <td><?php echo esc_html((string) ($order['unit'] ?? '—')); ?></td>
    </tr></tbody></table>
    <!-- Tables keep the two information blocks aligned in Dompdf, without browser grid CSS. -->
    <table class="cards"><tr>
    <?php foreach (['Livraison' => $delivery, 'Suivi de la commande' => $tracking] as $title => $fields) : ?>
        <?php if ($title === 'Suivi de la commande') : ?><td class="gap"></td><?php endif; ?>
        <td><div class="card"><h2><?php echo esc_html($title); ?></h2><div class="fields">
            <?php foreach ($fields as $i => [$label, $value]) : ?>
                <div class="field<?php echo $i === count($fields) - 1 ? ' last' : ''; ?>"><strong><?php echo esc_html($label); ?></strong><?php echo esc_html($value); ?></div>
            <?php endforeach; ?>
        </div></div></td>
    <?php endforeach; ?>
    </tr></table>
</div></body></html>
