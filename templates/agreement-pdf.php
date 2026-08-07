<?php
/**
 * @var array<string, mixed> $agreement_detail
 * @var string               $brand_logo_data_uri
 */

if (! defined('ABSPATH')) {
    exit;
}

$document_title = (string) ($agreement_detail['code'] ?? '');
$document_title = $document_title !== '' ? $document_title : __('Formulaire APA', 'plugin-apa-agadev');
$pdf_value = static function ($value): string {
    if (is_array($value)) {
        $items = array_map(static fn ($item): string => is_scalar($item) ? (string) $item : '', $value);
        $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));

        return $items === [] ? '—' : implode("\n", $items);
    }

    if ($value === null || $value === '') {
        return '—';
    }

    if (is_bool($value)) {
        return $value ? __('Oui', 'plugin-apa-agadev') : __('Non', 'plugin-apa-agadev');
    }

    return is_scalar($value) ? (string) $value : '—';
};
$is_long_value = static function (string $value): bool {
    return mb_strlen($value) > 72 || str_contains($value, "\n");
};
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html($document_title); ?></title>
    <style>
        @page { margin: 28px 34px 48px; }
        * { box-sizing: border-box; }
        body { color: #202722; font-family: "DejaVu Sans", sans-serif; font-size: 8.8pt; line-height: 1.42; margin: 0; }
        table { border-collapse: collapse; }
        .institutional-header { border-bottom: 2px solid #11663a; margin-bottom: 14px; padding: 0 0 12px; width: 100%; }
        .header-logo { padding-right: 15px; vertical-align: middle; width: 79px; }
        .header-logo img { display: block; height: auto; width: 64px; }
        .header-main { vertical-align: middle; }
        .institution-name { color: #11663a; font-size: 9pt; font-weight: bold; letter-spacing: .7px; text-transform: uppercase; }
        .platform-name { color: #68746d; font-size: 7.2pt; letter-spacing: .7px; margin-top: 3px; text-transform: uppercase; }
        .header-mark { color: #68746d; font-size: 7pt; letter-spacing: .8px; text-align: right; text-transform: uppercase; vertical-align: middle; width: 23%; }
        .document-identity { margin-bottom: 16px; width: 100%; }
        .document-kind { color: #dc6907; font-size: 8pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { color: #123c27; font-size: 21pt; line-height: 1.12; margin: 5px 0 0; overflow-wrap: break-word; }
        .document-reference { color: #68746d; font-size: 8pt; margin-top: 5px; }
        .header-status { text-align: right; vertical-align: middle; width: 24%; }
        .status { border-collapse: separate; margin-left: auto; }
        .status td { background: #fff; border: 1.5px solid #11663a; border-radius: 13px; color: #11663a; font-size: 8.5pt; font-weight: bold; height: 27px; padding: 0 13px; text-align: center; text-transform: uppercase; vertical-align: middle; }
        .dossier-heading { background: #eef3f0; border: 1px solid #cdd9d2; border-bottom: 0; color: #123c27; font-size: 8pt; font-weight: bold; letter-spacing: .7px; padding: 7px 11px; text-transform: uppercase; }
        .meta { border: 1px solid #cdd9d2; margin-bottom: 7px; padding: 8px 10px; width: 100%; }
        .meta td { padding: 5px 7px; vertical-align: top; width: 33.33%; }
        .meta-label, .field-label { color: #68746d; font-size: 7pt; font-weight: bold; letter-spacing: .35px; text-transform: uppercase; }
        .meta-value { color: #17251e; font-size: 9pt; margin-top: 2px; overflow-wrap: break-word; }
        .document-notice { border-left: 3px solid #dc6907; color: #59645e; font-size: 7.5pt; margin: 0 0 20px; padding: 6px 9px; }
        .section { margin: 0 0 18px; page-break-inside: auto; }
        .section-heading { background: #11663a; color: #fff; page-break-after: avoid; width: 100%; }
        .section-heading td { padding: 9px 11px; vertical-align: middle; }
        .section-number-cell { padding: 8px 0 8px 11px !important; width: 40px; }
        .section-number { border-collapse: separate; height: 25px; width: 25px; }
        .section-number td { background: #dc6907; border-radius: 13px; color: #fff; font-size: 10pt; font-weight: bold; height: 25px; padding: 0; text-align: center; vertical-align: middle; width: 25px; }
        .section-title { font-size: 12.5pt; font-weight: bold; line-height: 1.2; }
        .group { border: 1px solid #d8e3dc; border-top: 0; page-break-inside: auto; }
        .group-heading { background: #edf5f0; border-bottom: 1px solid #d8e3dc; color: #11663a; font-size: 10.5pt; font-weight: bold; padding: 8px 11px; page-break-after: avoid; }
        .entries { padding: 9px 10px 10px; }
        .entry { border-left: 3px solid #c7d9ce; margin: 0 0 8px; padding: 7px 9px 5px; page-break-inside: avoid; }
        .entry:last-child { margin-bottom: 0; }
        .entry--alternate { background: #f7f9f8; border-left-color: #dc6907; }
        .entry-title { color: #dc6907; font-size: 7.5pt; font-weight: bold; letter-spacing: .5px; margin-bottom: 5px; text-transform: uppercase; }
        .fields { table-layout: fixed; width: 100%; }
        .field { border-bottom: 1px solid #e4eae6; padding: 6px 8px 7px 0; page-break-inside: avoid; vertical-align: top; width: 50%; }
        .field + .field { padding-left: 10px; }
        .field--full { padding-right: 0; width: 100%; }
        .field-value { color: #17251e; font-size: 9.2pt; margin-top: 3px; overflow-wrap: break-word; white-space: pre-line; }
        .signature { border: 1px solid #cdd9d2; margin-top: 20px; padding: 11px 12px; page-break-inside: avoid; }
        .signature-title { color: #123c27; font-size: 9pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .signature code { color: #425047; display: block; font-family: "DejaVu Sans Mono", monospace; font-size: 6.8pt; margin-top: 4px; overflow-wrap: break-word; }
    </style>
</head>
<body>
    <table class="institutional-header">
        <tr>
            <td class="header-logo">
                <img src="<?php echo esc_attr($brand_logo_data_uri); ?>" alt="<?php echo esc_attr(__('Logo AGADEV', 'plugin-apa-agadev')); ?>">
            </td>
            <td class="header-main">
                <div class="institution-name"><?php esc_html_e('Agence Gabonaise pour le Développement de l’Économie Verte', 'plugin-apa-agadev'); ?></div>
                <div class="platform-name"><?php esc_html_e('Plateforme Maivou', 'plugin-apa-agadev'); ?></div>
            </td>
            <td class="header-mark"><?php esc_html_e('Document de synthèse', 'plugin-apa-agadev'); ?></td>
        </tr>
    </table>

    <table class="document-identity">
        <tr>
            <td>
                <div class="document-kind"><?php esc_html_e('Demande d’accès et de partage des avantages (APA)', 'plugin-apa-agadev'); ?></div>
                <h1><?php echo esc_html($document_title); ?></h1>
                <div class="document-reference"><?php echo esc_html(sprintf(__('Référence du dossier : %s', 'plugin-apa-agadev'), $document_title)); ?></div>
            </td>
            <td class="header-status">
                <table class="status" align="right"><tr><td><?php echo esc_html((string) ($agreement_detail['status_label'] ?? '—')); ?></td></tr></table>
            </td>
        </tr>
    </table>

    <div class="dossier-heading"><?php esc_html_e('Informations du dossier', 'plugin-apa-agadev'); ?></div>
    <table class="meta">
        <tr>
            <td><div class="meta-label"><?php esc_html_e('Titulaire', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['holder'] ?: '—')); ?></div></td>
            <td><div class="meta-label"><?php esc_html_e('Date de création', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['created_at'] ?? '—')); ?></div></td>
            <td><div class="meta-label"><?php esc_html_e('Consentement', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['consented_at'] ?? '—')); ?></div></td>
        </tr>
        <tr>
            <td><div class="meta-label"><?php esc_html_e('Début de validité', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['starts_at'] ?? '—')); ?></div></td>
            <td><div class="meta-label"><?php esc_html_e('Fin de validité', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['ends_at'] ?? '—')); ?></div></td>
            <td></td>
        </tr>
    </table>
    <div class="document-notice"><?php esc_html_e('Cette synthèse reprend les informations fonctionnelles enregistrées dans Maivou pour la demande référencée ci-dessus.', 'plugin-apa-agadev'); ?></div>

    <?php foreach ((array) ($agreement_detail['sections'] ?? []) as $section_index => $section) : ?>
        <?php if (! is_array($section)) { continue; } ?>
        <section class="section">
            <table class="section-heading">
                <tr>
                    <td class="section-number-cell"><table class="section-number"><tr><td><?php echo esc_html((string) ($section_index + 1)); ?></td></tr></table></td>
                    <td class="section-title"><?php echo esc_html((string) ($section['label'] ?? '')); ?></td>
                </tr>
            </table>

            <?php foreach ((array) ($section['groups'] ?? []) as $group) : ?>
                <?php if (! is_array($group)) { continue; } ?>
                <?php $entries = array_values(array_filter((array) ($group['entries'] ?? []), 'is_array')); ?>
                <div class="group">
                    <div class="group-heading"><?php echo esc_html((string) ($group['label'] ?? '')); ?></div>
                    <div class="entries">
                        <?php foreach ($entries as $entry_index => $entry) : ?>
                            <?php
                            $fields = array_values(array_filter((array) ($entry['fields'] ?? []), 'is_array'));
                            $pending_cell = false;
                            ?>
                            <div class="entry<?php echo $entry_index % 2 === 1 ? ' entry--alternate' : ''; ?>">
                                <?php if (count($entries) > 1) : ?>
                                    <div class="entry-title"><?php echo esc_html(sprintf(__('Entrée %d', 'plugin-apa-agadev'), $entry_index + 1)); ?></div>
                                <?php endif; ?>
                                <table class="fields">
                                    <?php foreach ($fields as $field) : ?>
                                        <?php
                                        $value = $pdf_value($field['display_value'] ?? null);
                                        $full_width = $is_long_value($value);

                                        if ($full_width && $pending_cell) {
                                            echo '<td class="field"></td></tr>';
                                            $pending_cell = false;
                                        }

                                        if (! $pending_cell) {
                                            echo '<tr>';
                                        }
                                        ?>
                                        <td class="field<?php echo $full_width ? ' field--full' : ''; ?>"<?php echo $full_width ? ' colspan="2"' : ''; ?>>
                                            <div class="field-label"><?php echo esc_html((string) ($field['label'] ?? '')); ?></div>
                                            <div class="field-value"><?php echo nl2br(esc_html($value)); ?></div>
                                        </td>
                                        <?php
                                        if ($full_width || $pending_cell) {
                                            echo '</tr>';
                                            $pending_cell = false;
                                        } else {
                                            $pending_cell = true;
                                        }
                                        ?>
                                    <?php endforeach; ?>
                                    <?php if ($pending_cell) : ?><td class="field"></td></tr><?php endif; ?>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>

    <?php if ((string) ($agreement_detail['signature'] ?? '') !== '') : ?>
        <section class="signature">
            <div class="signature-title"><?php esc_html_e('Signature numérique associée au dossier', 'plugin-apa-agadev'); ?></div>
            <div class="field-label"><?php esc_html_e('Empreinte enregistrée', 'plugin-apa-agadev'); ?></div>
            <code><?php echo esc_html((string) $agreement_detail['signature']); ?></code>
        </section>
    <?php endif; ?>
</body>
</html>
