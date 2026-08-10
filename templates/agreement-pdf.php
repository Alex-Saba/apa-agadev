<?php
/**
 * @var array<string, mixed> $agreement_detail
 * @var string               $brand_logo_data_uri
 */

if (! defined('ABSPATH')) {
    exit;
}

$document_reference = trim((string) ($agreement_detail['code'] ?? ''));
$document_reference = $document_reference !== ''
    ? $document_reference
    : __('À attribuer par Maivou', 'plugin-apa-agadev');
$document_title = __('Formulaire de demande APA', 'plugin-apa-agadev');
$status_label = trim((string) ($agreement_detail['status_label'] ?? ''));
$status_label = $status_label !== '' ? $status_label : '—';

if ('pending' === (string) ($agreement_detail['status'] ?? '')) {
    $status_label = __('En attente d’instruction', 'plugin-apa-agadev');
}
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
        @page { margin: 56px 42px 52px; }
        * { box-sizing: border-box; }
        body { color: #202722; font-family: "DejaVu Sans", sans-serif; font-size: 8.4pt; line-height: 1.35; margin: 0; }
        table { border-collapse: collapse; }
        .national-header { left: 0; margin: 0; position: fixed; right: 0; top: -28px; width: 100%; }
        .flag-rule { height: 4px; width: 154px; }
        .flag-rule td { height: 4px; padding: 0; width: 33.33%; }
        .flag-green { background: #07964a; }
        .flag-yellow { background: #f6ce26; }
        .flag-blue { background: #3874c6; }
        .national-motto { color: #657069; font-size: 7.2pt; letter-spacing: .2px; text-align: right; text-transform: uppercase; vertical-align: top; }
        .institutional-header { margin-bottom: 28px; width: 100%; }
        .header-logo { padding-right: 13px; vertical-align: middle; width: 92px; }
        .header-logo img { display: block; height: auto; width: 82px; }
        .header-main { vertical-align: middle; }
        .institution-name { color: #124b31; font-size: 10.5pt; font-weight: normal; letter-spacing: .2px; line-height: 1.25; text-transform: uppercase; }
        .platform-name { color: #68746d; font-size: 7.2pt; letter-spacing: .45px; margin-top: 4px; text-transform: uppercase; }
        .header-mark { color: #657069; font-size: 7.5pt; font-weight: bold; letter-spacing: .35px; line-height: 1.35; text-align: right; text-transform: uppercase; vertical-align: middle; width: 22%; }
        .document-identity { margin-bottom: 18px; width: 100%; }
        .document-kind { color: #e36a00; font-size: 8.2pt; font-weight: bold; letter-spacing: .55px; text-transform: uppercase; }
        h1 { color: #123c27; font-size: 22pt; line-height: 1.12; margin: 8px 0 0; }
        .document-reference { color: #68746d; font-size: 8.1pt; margin-top: 7px; }
        .header-status { text-align: right; vertical-align: bottom; width: 27%; }
        .status { margin-left: auto; width: 100%; }
        .status td { background: #fff; border: 1.3px solid #e36a00; color: #e36a00; font-size: 8pt; font-weight: bold; height: 36px; line-height: 1.2; padding: 5px 10px; text-align: center; text-transform: uppercase; vertical-align: middle; }
        .dossier-heading { background: #edf5f0; border: 1px solid #cbdcd2; border-bottom: 0; color: #08713f; font-size: 10.5pt; font-weight: bold; padding: 8px 11px; }
        .meta { border: 1px solid #cbdcd2; margin-bottom: 14px; table-layout: fixed; width: 100%; }
        .meta td { border-right: 1px solid #cbdcd2; border-bottom: 1px solid #cbdcd2; padding: 9px 11px; vertical-align: top; width: 33.33%; }
        .meta td:last-child { border-right: 0; }
        .meta tr:last-child td { border-bottom: 0; }
        .meta-label, .field-label { color: #68746d; font-size: 7pt; font-weight: bold; letter-spacing: .35px; text-transform: uppercase; }
        .meta-value { color: #17251e; font-size: 9.2pt; font-weight: bold; margin-top: 3px; overflow-wrap: break-word; }
        .section { margin: 0 0 14px; page-break-inside: auto; }
        .section-heading { background: #11663a; color: #fff; page-break-after: avoid; width: 100%; }
        .section-heading td { padding: 10px 13px; vertical-align: middle; }
        .section-number-cell { background: #ed7100; padding: 0 !important; text-align: center; width: 37px; }
        .section-number { height: 37px; width: 37px; }
        .section-number td { color: #fff; font-size: 10.5pt; font-weight: bold; height: 37px; padding: 0; text-align: center; vertical-align: middle; width: 37px; }
        .section-title { font-size: 13pt; font-weight: bold; line-height: 1.15; }
        .group { border: 1px solid #cbdcd2; border-top: 0; page-break-inside: auto; }
        .group-heading { background: #edf5f0; border-bottom: 1px solid #cbdcd2; color: #08713f; font-size: 10.5pt; font-weight: bold; padding: 8px 12px; page-break-after: avoid; }
        .entries { padding: 0; }
        .entry { margin: 0; page-break-inside: avoid; }
        .entry--alternate { background: #f8faf9; }
        .entry-title { background: #fafbf9; color: #e36a00; font-size: 7.4pt; font-weight: bold; letter-spacing: .4px; padding: 7px 11px; text-transform: uppercase; }
        .fields { table-layout: fixed; width: 100%; }
        .field { border-right: 1px solid #dce6e0; border-bottom: 1px solid #dce6e0; padding: 8px 11px 9px; page-break-inside: avoid; vertical-align: top; width: 50%; }
        .field:last-child { border-right: 0; }
        .field--full { border-right: 0; width: 100%; }
        .field-value { color: #17251e; font-size: 9.1pt; font-weight: bold; margin-top: 4px; overflow-wrap: break-word; white-space: pre-line; }
        .document-notice { background: #fff7ef; border: 1px solid #efc59e; color: #657069; font-size: 7.3pt; margin: 18px 0 0; padding: 8px 10px; }
        .notice-label { color: #e36a00; font-weight: bold; padding-right: 11px; text-transform: uppercase; }
        .signature { border: 1px solid #cbdcd2; margin-top: 15px; padding: 11px 12px; page-break-inside: avoid; }
        .signature-title { color: #08713f; font-size: 9pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .signature code { color: #425047; display: block; font-family: "DejaVu Sans Mono", monospace; font-size: 6.8pt; margin-top: 4px; overflow-wrap: break-word; }
        .administration { margin-top: 18px; page-break-inside: avoid; }
        .administration-subtitle { background: #edf5f0; border: 1px solid #cbdcd2; border-top: 0; color: #08713f; font-size: 10pt; font-weight: bold; padding: 8px 12px; }
        .administration-grid { border: 1px solid #cbdcd2; table-layout: fixed; width: 100%; }
        .administration-grid td { border-bottom: 1px solid #cbdcd2; border-right: 1px solid #cbdcd2; height: 54px; padding: 9px 11px; vertical-align: top; width: 50%; }
        .administration-grid td:last-child { border-right: 0; }
        .administration-grid tr:last-child td { border-bottom: 0; }
        .administration-grid .administration-observations { height: 66px; }
        .administration-choice { color: #17251e; font-size: 9pt; font-weight: bold; margin-top: 4px; }
        .administration-date { color: #17251e; font-size: 10pt; margin-top: 8px; }
        .administration-signatures { border: 1px solid #6f8176; margin-top: 9px; table-layout: fixed; width: 100%; }
        .administration-signatures td { border-right: 1px solid #cbdcd2; height: 68px; padding: 9px 11px; vertical-align: top; width: 50%; }
        .administration-signatures td:last-child { border-right: 0; }
        .authenticity { color: #657069; font-size: 7.3pt; line-height: 1.35; margin: 11px 7px 0; }
        .authenticity-title { font-weight: bold; margin-bottom: 3px; text-transform: uppercase; }
    </style>
</head>
<body>
    <table class="national-header">
        <tr>
            <td><table class="flag-rule"><tr><td class="flag-green"></td><td class="flag-yellow"></td><td class="flag-blue"></td></tr></table></td>
            <td class="national-motto"><?php esc_html_e('République Gabonaise · Union · Travail · Justice', 'plugin-apa-agadev'); ?></td>
        </tr>
    </table>

    <table class="institutional-header">
        <tr>
            <td class="header-logo">
                <img src="<?php echo esc_attr($brand_logo_data_uri); ?>" alt="<?php echo esc_attr(__('Logo AGADEV', 'plugin-apa-agadev')); ?>">
            </td>
            <td class="header-main">
                <div class="institution-name"><?php esc_html_e('Agence Gabonaise pour le Développement de l’Économie Verte', 'plugin-apa-agadev'); ?></div>
                <div class="platform-name"><?php esc_html_e('Plateforme Maivou', 'plugin-apa-agadev'); ?></div>
            </td>
            <td class="header-mark"><?php esc_html_e('Document administratif', 'plugin-apa-agadev'); ?></td>
        </tr>
    </table>

    <table class="document-identity">
        <tr>
            <td>
                <div class="document-kind"><?php esc_html_e('Demande d’accès et de partage des avantages (APA)', 'plugin-apa-agadev'); ?></div>
                <h1><?php echo esc_html($document_title); ?></h1>
                <div class="document-reference"><?php echo esc_html(sprintf(__('Référence du dossier : %s', 'plugin-apa-agadev'), $document_reference)); ?></div>
            </td>
            <td class="header-status">
                <table class="status" align="right"><tr><td><?php echo esc_html($status_label); ?></td></tr></table>
            </td>
        </tr>
    </table>

    <div class="dossier-heading"><?php esc_html_e('Informations du dossier', 'plugin-apa-agadev'); ?></div>
    <table class="meta">
        <tr>
            <td><div class="meta-label"><?php esc_html_e('Titulaire', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['holder'] ?: '—')); ?></div></td>
            <td><div class="meta-label"><?php esc_html_e('Date de création', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['created_at'] ?? '—')); ?></div></td>
            <td><div class="meta-label"><?php esc_html_e('Type de demande', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php esc_html_e('Demande APA', 'plugin-apa-agadev'); ?></div></td>
        </tr>
        <tr>
            <td><div class="meta-label"><?php esc_html_e('Début de validité', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['starts_at'] ?? '—')); ?></div></td>
            <td><div class="meta-label"><?php esc_html_e('Fin de validité', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['ends_at'] ?? '—')); ?></div></td>
            <td><div class="meta-label"><?php esc_html_e('Consentement', 'plugin-apa-agadev'); ?></div><div class="meta-value"><?php echo esc_html((string) ($agreement_detail['consented_at'] ?? '—')); ?></div></td>
        </tr>
    </table>

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
                                <div class="entry-title"><?php echo esc_html(sprintf(__('Entrée %02d', 'plugin-apa-agadev'), $entry_index + 1)); ?></div>
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

    <table class="document-notice">
        <tr>
            <td class="notice-label"><?php esc_html_e('Note', 'plugin-apa-agadev'); ?></td>
            <td><?php esc_html_e('Les informations ci-dessus constituent la synthèse de la demande transmise. Toute modification après soumission doit faire l’objet d’une mise à jour tracée dans Maivou.', 'plugin-apa-agadev'); ?></td>
        </tr>
    </table>

    <?php if ((string) ($agreement_detail['signature'] ?? '') !== '') : ?>
        <section class="signature">
            <div class="signature-title"><?php esc_html_e('Signature numérique associée au dossier', 'plugin-apa-agadev'); ?></div>
            <div class="field-label"><?php esc_html_e('Empreinte enregistrée', 'plugin-apa-agadev'); ?></div>
            <code><?php echo esc_html((string) $agreement_detail['signature']); ?></code>
        </section>
    <?php endif; ?>

    <section class="administration">
        <table class="section-heading">
            <tr>
                <td class="section-number-cell"><table class="section-number"><tr><td>4</td></tr></table></td>
                <td class="section-title"><?php esc_html_e('Cadre réservé à l’administration', 'plugin-apa-agadev'); ?></td>
            </tr>
        </table>
        <div class="administration-subtitle"><?php esc_html_e('Instruction et décision', 'plugin-apa-agadev'); ?></div>
        <table class="administration-grid">
            <tr>
                <td>
                    <div class="field-label"><?php esc_html_e('Recevabilité du dossier', 'plugin-apa-agadev'); ?></div>
                    <div class="administration-choice">□ <?php esc_html_e('Complet', 'plugin-apa-agadev'); ?> &nbsp; □ <?php esc_html_e('Incomplet', 'plugin-apa-agadev'); ?></div>
                </td>
                <td>
                    <div class="field-label"><?php esc_html_e('Décision', 'plugin-apa-agadev'); ?></div>
                    <div class="administration-choice">□ <?php esc_html_e('Validée', 'plugin-apa-agadev'); ?> &nbsp; □ <?php esc_html_e('Rejetée', 'plugin-apa-agadev'); ?></div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="field-label"><?php esc_html_e('Date de début de validité', 'plugin-apa-agadev'); ?></div>
                    <div class="administration-date">___ / ___ / _____</div>
                </td>
                <td>
                    <div class="field-label"><?php esc_html_e('Date de fin de validité', 'plugin-apa-agadev'); ?></div>
                    <div class="administration-date">___ / ___ / _____</div>
                </td>
            </tr>
            <tr>
                <td class="administration-observations" colspan="2">
                    <div class="field-label"><?php esc_html_e('Observations / motifs', 'plugin-apa-agadev'); ?></div>
                </td>
            </tr>
        </table>
        <table class="administration-signatures">
            <tr>
                <td><div class="field-label"><?php esc_html_e('Nom et qualité de l’autorité compétente', 'plugin-apa-agadev'); ?></div></td>
                <td><div class="field-label"><?php esc_html_e('Date, cachet et signature', 'plugin-apa-agadev'); ?></div></td>
            </tr>
        </table>
        <div class="authenticity">
            <div class="authenticity-title"><?php esc_html_e('Authenticité du document', 'plugin-apa-agadev'); ?></div>
            <div><?php esc_html_e('La version officielle est celle conservée dans Maivou. La référence unique, la date de génération et l’empreinte de vérification devront être ajoutées automatiquement lors de la génération définitive.', 'plugin-apa-agadev'); ?></div>
        </div>
    </section>
</body>
</html>
