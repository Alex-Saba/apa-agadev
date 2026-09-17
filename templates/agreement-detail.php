<?php
/**
 * Read-only detail of one APA agreement.
 *
 * @var array<string, mixed> $agreement_detail
 * @var string $back_url
 * @var string $download_url
 */

if (! defined('ABSPATH')) {
    exit;
}

$detail_title = (string) ($agreement_detail['code'] ?? '');
$detail_title = $detail_title !== '' ? $detail_title : __('Formulaire APA', 'plugin-apa-agadev');
$sections = array_values(array_filter((array) ($agreement_detail['sections'] ?? []), 'is_array'));
$documents = array_values(array_filter((array) ($agreement_detail['documents'] ?? []), 'is_array'));
$field_count = 0;

foreach ($sections as $section) {
    foreach ((array) ($section['groups'] ?? []) as $group) {
        if (! is_array($group)) {
            continue;
        }

        foreach ((array) ($group['entries'] ?? []) as $entry) {
            if (is_array($entry)) {
                $field_count += count(array_filter((array) ($entry['fields'] ?? []), 'is_array'));
            }
        }
    }
}

$is_long_detail = count($sections) > 3 || $field_count > 20;
$section_id_prefix = 'apa-agreement-' . max(1, (int) ($agreement_detail['id'] ?? 0)) . '-section-';
$display_value = static function ($value): string {
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
?>

<article class="acl_shortcode_agreement_detail acl_shortcode_article" data-apa-agreement-detail data-apa-agreement-long="<?php echo $is_long_detail ? '1' : '0'; ?>">
    <header class="acl_shortcode_agreement_detail_header acl_shortcode_div">
        <div>
            <span class="acl_shortcode_agreement_detail_eyebrow"><?php esc_html_e('Demande APA', 'plugin-apa-agadev'); ?></span>
            <h3 class="acl_shortcode_agreement_detail_title acl_shortcode_h3"><?php echo esc_html($detail_title); ?></h3>
        </div>
        <span class="acl_shortcode_agreements_status acl_shortcode_agreements_status--<?php echo esc_attr(sanitize_html_class((string) ($agreement_detail['status'] ?? 'unknown'))); ?>">
            <?php echo esc_html((string) ($agreement_detail['status_label'] ?? '—')); ?>
        </span>
    </header>

    <div class="acl_shortcode_agreement_detail_actions acl_shortcode_div">
        <a class="acl_shortcode_agreement_back acl_shortcode_button_button" href="<?php echo esc_url($back_url); ?>"><?php esc_html_e('Retour à mes agréments', 'plugin-apa-agadev'); ?></a>
        <a class="acl_shortcode_agreement_download acl_shortcode_button_button" href="<?php echo esc_url($download_url); ?>"><?php esc_html_e('Télécharger le PDF', 'plugin-apa-agadev'); ?></a>
    </div>

    <dl class="acl_shortcode_agreement_meta">
        <div><dt><?php esc_html_e('Titulaire', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html((string) ($agreement_detail['holder'] ?: '—')); ?></dd></div>
        <div><dt><?php esc_html_e('Date de création', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html((string) ($agreement_detail['created_at'] ?? '—')); ?></dd></div>
        <div><dt><?php esc_html_e('Consentement', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html((string) ($agreement_detail['consented_at'] ?? '—')); ?></dd></div>
        <div><dt><?php esc_html_e('Début de validité', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html((string) ($agreement_detail['starts_at'] ?? '—')); ?></dd></div>
        <div><dt><?php esc_html_e('Fin de validité', 'plugin-apa-agadev'); ?></dt><dd><?php echo esc_html((string) ($agreement_detail['ends_at'] ?? '—')); ?></dd></div>
    </dl>

    <?php if ($documents !== []) : ?>
        <section class="acl_shortcode_agreement_documents" aria-labelledby="apa-agreement-documents-title">
            <div class="acl_shortcode_agreement_documents_header">
                <span class="acl_shortcode_agreement_documents_icon" aria-hidden="true">&#128196;</span>
                <div>
                    <h4 id="apa-agreement-documents-title"><?php esc_html_e('Documents joints', 'plugin-apa-agadev'); ?></h4>
                    <p><?php esc_html_e('Documents enregistrés avec cette demande APA.', 'plugin-apa-agadev'); ?></p>
                </div>
            </div>
            <ul class="acl_shortcode_agreement_documents_list">
                <?php foreach ($documents as $document) : ?>
                    <li>
                        <strong><?php echo esc_html((string) ($document['name'] ?? '')); ?></strong>
                        <span>
                            <?php
                            $document_meta = array_values(array_filter([
                                (string) ($document['context'] ?? ''),
                                (string) ($document['mime_type'] ?? ''),
                                (string) ($document['size'] ?? ''),
                            ], static fn (string $item): bool => $item !== ''));
                            echo esc_html(implode(' · ', $document_meta));
                            ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="acl_shortcode_agreement_content<?php echo count($sections) > 1 ? '' : ' acl_shortcode_agreement_content--single'; ?> acl_shortcode_div">
        <?php if (count($sections) > 1) : ?>
            <aside class="acl_shortcode_agreement_navigation acl_shortcode_div">
                <nav class="acl_shortcode_agreement_toc" aria-label="<?php echo esc_attr(__('Sections de la demande', 'plugin-apa-agadev')); ?>">
                    <?php foreach ($sections as $section_index => $section) : ?>
                        <a href="#<?php echo esc_attr($section_id_prefix . $section_index); ?>" data-apa-agreement-section-link>
                            <span><?php echo esc_html((string) ($section_index + 1)); ?></span>
                            <strong><?php echo esc_html((string) ($section['label'] ?? '')); ?></strong>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="acl_shortcode_agreement_section_actions" aria-label="<?php echo esc_attr(__('Affichage des sections', 'plugin-apa-agadev')); ?>">
                    <button type="button" data-apa-agreement-sections-action="expand"><?php esc_html_e('Tout déplier', 'plugin-apa-agadev'); ?></button>
                    <button type="button" data-apa-agreement-sections-action="collapse"><?php esc_html_e('Tout replier', 'plugin-apa-agadev'); ?></button>
                </div>
            </aside>
        <?php endif; ?>

        <div class="acl_shortcode_agreement_sections acl_shortcode_div">
        <?php foreach ($sections as $section_index => $section) : ?>
            <?php
            $section_groups = array_values(array_filter((array) ($section['groups'] ?? []), 'is_array'));
            $section_entry_count = 0;
            foreach ($section_groups as $section_group) {
                $section_entry_count += count(array_filter((array) ($section_group['entries'] ?? []), 'is_array'));
            }
            $section_open = ! $is_long_detail || $section_index === 0;
            ?>
            <details id="<?php echo esc_attr($section_id_prefix . $section_index); ?>" class="acl_shortcode_agreement_section" data-apa-agreement-section<?php echo $section_open ? ' open' : ''; ?>>
                <summary class="acl_shortcode_agreement_section_summary" aria-expanded="<?php echo $section_open ? 'true' : 'false'; ?>">
                    <span class="acl_shortcode_agreement_section_number"><?php echo esc_html((string) ($section_index + 1)); ?></span>
                    <span class="acl_shortcode_agreement_section_heading">
                        <strong><?php echo esc_html((string) ($section['label'] ?? '')); ?></strong>
                        <small>
                            <?php
                            echo esc_html(sprintf(
                                __('%1$d groupe(s) · %2$d entrée(s)', 'plugin-apa-agadev'),
                                count($section_groups),
                                $section_entry_count
                            ));
                            ?>
                        </small>
                    </span>
                    <span class="acl_shortcode_agreement_section_chevron" aria-hidden="true"></span>
                </summary>

                <div class="acl_shortcode_agreement_section_content">

                <?php foreach ($section_groups as $group) : ?>
                    <?php $entries = array_values(array_filter((array) ($group['entries'] ?? []), 'is_array')); ?>
                    <div class="acl_shortcode_agreement_group acl_shortcode_div">
                        <h5><?php echo esc_html((string) ($group['label'] ?? '')); ?></h5>

                        <?php foreach ($entries as $entry_index => $entry) : ?>
                            <div class="<?php echo esc_attr(count($entries) > 1 ? 'acl_shortcode_agreement_row acl_shortcode_div' : 'acl_shortcode_agreement_entry acl_shortcode_div'); ?>">
                                <?php if (count($entries) > 1) : ?>
                                    <h6><?php echo esc_html(sprintf(__('Entrée %d', 'plugin-apa-agadev'), $entry_index + 1)); ?></h6>
                                <?php endif; ?>
                                <dl class="acl_shortcode_agreement_fields">
                                    <?php foreach ((array) ($entry['fields'] ?? []) as $field) : ?>
                                        <?php if (! is_array($field)) { continue; } ?>
                                        <div class="acl_shortcode_agreement_field acl_shortcode_div">
                                            <dt><?php echo esc_html((string) ($field['label'] ?? '')); ?></dt>
                                            <dd><?php echo nl2br(esc_html($display_value($field['display_value'] ?? null))); ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
        </div>
    </div>
</article>
