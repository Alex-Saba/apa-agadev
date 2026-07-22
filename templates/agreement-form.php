<?php
/**
 * Interactive APA agreement form generated from Maivou's filtered catalog.
 *
 * @var array<string, mixed> $catalog
 * @var array<string, mixed> $remote_options
 * @var array<string, mixed> $submitted
 * @var array<string, mixed>|null $submission
 * @var string $layout
 */
if (! defined('ABSPATH')) {
    exit;
}

$sections = is_array($catalog['sections'] ?? null) ? $catalog['sections'] : [];
$is_modal_layout = isset($layout) && 'modal' === $layout;
$submission_succeeded = is_array($submission) && ! empty($submission['ok']);
$section_groups = [];
$steps = [];

// One navigation step represents one Maivou section. Its fields and all of its
// role-filtered subsections remain visible together without changing payload paths.
foreach ($sections as $section_key => $section) {
    if (! is_string($section_key) || ! is_array($section)) {
        continue;
    }

    $section_title = (string) ($section['title'] ?? $section_key);
    $section_fields = is_array($section['fields'] ?? null) ? $section['fields'] : [];
    $subsections = [];

    foreach ((array) ($section['subsections'] ?? []) as $subsection_key => $subsection) {
        if (! is_string($subsection_key) || ! is_array($subsection)) {
            continue;
        }

        $subsections[] = [
            'key' => $subsection_key,
            'title' => (string) ($subsection['title'] ?? $subsection_key),
            'description' => (string) ($subsection['description'] ?? ''),
            'fields' => is_array($subsection['fields'] ?? null) ? $subsection['fields'] : [],
            'benefits' => is_array($subsection['benefits'] ?? null) ? $subsection['benefits'] : [],
        ];
    }

    if ($section_fields === [] && $subsections === []) {
        continue;
    }

    $section_index = count($section_groups);
    $section_groups[] = [
        'key' => $section_key,
        'title' => $section_title,
    ];
    $steps[] = [
        'section_key' => $section_key,
        'section_index' => $section_index,
        'title' => $section_title,
        'description' => (string) ($section['description'] ?? ''),
        'fields' => $section_fields,
        'subsections' => $subsections,
    ];
}

$section_count = count($section_groups);
$step_count = count($steps);

$is_list = static function (array $value): bool {
    return $value !== [] && array_keys($value) === range(0, count($value) - 1);
};

$field_options = static function (array $field) use ($remote_options): array {
    $endpoint = isset($field['optionsEndpoint']) ? (string) $field['optionsEndpoint'] : '';

    // Maivou may resolve a select with both an empty `options` array and an
    // `optionsEndpoint`; in that case the endpoint remains the source of truth.
    if ('' === $endpoint) {
        return is_array($field['options'] ?? null) ? $field['options'] : [];
    }

    $items = is_array($remote_options[$endpoint] ?? null) ? $remote_options[$endpoint] : [];
    $options = [];

    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }

        // Match the identifiers consumed by Maivou's own APA form renderer.
        if ('/api/products' === $endpoint) {
            $value = $item['uuid'] ?? $item['code'] ?? $item['id'] ?? null;
        } elseif ('/api/zones' === $endpoint) {
            $value = $item['id'] ?? $item['uuid'] ?? $item['code'] ?? null;
        } else {
            $value = $item['code'] ?? $item['uuid'] ?? $item['id'] ?? null;
        }

        if (null === $value) {
            continue;
        }

        $label = $item['name'] ?? $item['title'] ?? $item['code'] ?? null;
        if (null === $label && isset($item['province_name'], $item['department_name'])) {
            $label = (string) $item['province_name'] . ' — ' . (string) $item['department_name'];
        }

        $options[(string) $value] = (string) ($label ?? $value);
    }

    return $options;
};

$render_input = static function (
    string $name,
    string $key,
    array $field,
    $value
) use ($field_options): void {
    $type = (string) ($field['type'] ?? 'text');
    $label = (string) ($field['label'] ?? $key);
    $required = ! empty($field['required']);
    $id = 'apa-' . substr(md5($name), 0, 12);
    $options = $field_options($field);
    $width_class = [
        '1/2' => 'acl_shortcode_apa_field--half',
        '1/3' => 'acl_shortcode_apa_field--third',
        '2/3' => 'acl_shortcode_apa_field--two-thirds',
    ][(string) ($field['width'] ?? '')] ?? 'acl_shortcode_apa_field--full';
    ?>
    <div class="acl_shortcode_field acl_shortcode_apa_field <?php echo esc_attr($width_class); ?> acl_shortcode_div">
        <?php if ('checkbox' !== $type) : ?>
            <label class="acl_shortcode_label"<?php if ('multiselect' === $type) : ?> id="<?php echo esc_attr($id); ?>-label"<?php else : ?> for="<?php echo esc_attr($id); ?>"<?php endif; ?>>
                <?php echo esc_html($label); ?>
                <?php if ($required) : ?><span class="acl_shortcode_required acl_shortcode_span" aria-hidden="true"> *</span><?php endif; ?>
            </label>
        <?php endif; ?>

        <div class="acl_shortcode_control acl_shortcode_div">
        <?php if ('textarea' === $type) : ?>
            <textarea class="acl_shortcode_textarea" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>"<?php echo $required ? ' required' : ''; ?>><?php echo esc_textarea((string) $value); ?></textarea>
        <?php elseif ('select' === $type) : ?>
            <?php $selected_values = is_array($value) ? array_map('strval', $value) : [(string) $value]; ?>
            <select class="acl_shortcode_select" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>"<?php echo $required ? ' required' : ''; ?>>
                <option class="acl_shortcode_option" value=""><?php esc_html_e('Sélectionner', 'plugin-apa-agadev'); ?></option>
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <option class="acl_shortcode_option" value="<?php echo esc_attr((string) $option_value); ?>"<?php echo in_array((string) $option_value, $selected_values, true) ? ' selected' : ''; ?>><?php echo esc_html((string) $option_label); ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ('multiselect' === $type) : ?>
            <?php $selected_values = is_array($value) ? array_map('strval', $value) : [(string) $value]; ?>
            <div class="acl_shortcode_apa_choices acl_shortcode_div" role="group" aria-labelledby="<?php echo esc_attr($id); ?>-label"<?php echo $required ? ' aria-required="true"' : ''; ?>>
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <?php $option_id = $id . '-' . substr(md5((string) $option_value), 0, 8); ?>
                    <label class="acl_shortcode_apa_choice" for="<?php echo esc_attr($option_id); ?>">
                        <input class="acl_shortcode_input_checkbox" id="<?php echo esc_attr($option_id); ?>" type="checkbox" name="<?php echo esc_attr($name . '[]'); ?>" value="<?php echo esc_attr((string) $option_value); ?>"<?php checked(in_array((string) $option_value, $selected_values, true)); ?>>
                        <span><?php echo esc_html((string) $option_label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php elseif ('radio' === $type) : ?>
            <fieldset>
                <legend class="screen-reader-text"><?php echo esc_html($label); ?></legend>
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <label class="acl_shortcode_option">
                        <input class="acl_shortcode_input_radio" type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $option_value); ?>"<?php checked((string) $value, (string) $option_value); ?><?php echo $required ? ' required' : ''; ?>>
                        <?php echo esc_html((string) $option_label); ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php elseif ('checkbox' === $type) : ?>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="0">
            <label class="acl_shortcode_option" for="<?php echo esc_attr($id); ?>">
                <input class="acl_shortcode_input_checkbox" id="<?php echo esc_attr($id); ?>" type="checkbox" name="<?php echo esc_attr($name); ?>" value="1"<?php checked(in_array($value, [true, 1, '1'], true)); ?><?php echo $required ? ' required' : ''; ?>>
                <?php echo esc_html($label); ?><?php if ($required) : ?><span class="acl_shortcode_required acl_shortcode_span" aria-hidden="true"> *</span><?php endif; ?>
            </label>
        <?php elseif (in_array($type, ['file', 'dropzone'], true)) : ?>
            <?php $allows_multiple_files = 'dropzone' === $type; ?>
            <div class="acl_shortcode_apa_dropzone acl_shortcode_div" data-apa-dropzone>
                <input
                    class="acl_shortcode_apa_file_input"
                    id="<?php echo esc_attr($id); ?>"
                    type="file"
                    name="apa_agadev_document_pending[]"
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    data-apa-file-input
                    data-apa-file-path="<?php echo esc_attr($name); ?>"
                    <?php echo $allows_multiple_files ? ' multiple' : ''; ?>
                    <?php echo $required ? ' required' : ''; ?>
                >
                <strong><?php esc_html_e('Faites glisser le fichier ici', 'plugin-apa-agadev'); ?></strong>
                <span data-apa-file-summary><?php esc_html_e('ou cliquez pour sélectionner un fichier', 'plugin-apa-agadev'); ?></span>
                <small><?php esc_html_e('PDF, JPG ou PNG — 5 Mo maximum par fichier.', 'plugin-apa-agadev'); ?></small>
            </div>
        <?php else : ?>
            <?php $html_type = in_array($type, ['date', 'email', 'number'], true) ? $type : 'text'; ?>
            <input class="acl_shortcode_input_text" id="<?php echo esc_attr($id); ?>" type="<?php echo esc_attr($html_type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>"<?php echo $required ? ' required' : ''; ?>>
        <?php endif; ?>
        </div>
    </div>
    <?php
};

$render_fields = null;
$render_fields = static function (
    array $fields,
    string $prefix,
    array $values
) use (&$render_fields, $is_list, $render_input): void {
    foreach ($fields as $key => $field) {
        if (! is_string($key) || ! is_array($field)) {
            continue;
        }

        if ($is_list($field)) {
            $field = is_array($field[0] ?? null) ? $field[0] : [];
        }

        $type = (string) ($field['type'] ?? 'text');
        $name = $prefix . '[' . $key . ']';
        $value = $values[$key] ?? null;

        if ('repeater' !== $type) {
            $render_input($name, $key, $field, $value);
            continue;
        }

        $rows = is_array($value) && $value !== [] ? $value : [[]];
        $child_fields = is_array($field['fields'] ?? null) ? $field['fields'] : [];
        ?>
        <fieldset class="acl_shortcode_fields acl_shortcode_apa_repeater" data-apa-repeater>
            <legend><?php echo esc_html((string) ($field['label'] ?? $key)); ?><?php if (! empty($field['required'])) : ?><span aria-hidden="true"> *</span><?php endif; ?></legend>
            <div data-apa-repeater-rows>
                <?php foreach ($rows as $index => $row) : ?>
                    <div class="acl_shortcode_card acl_shortcode_apa_repeater_row" data-apa-repeater-row data-apa-row-index="<?php echo esc_attr((string) $index); ?>">
                        <?php $render_fields($child_fields, $name . '[' . (string) $index . ']', is_array($row) ? $row : []); ?>
                        <button type="button" class="acl_shortcode_btn acl_shortcode_button_button acl_shortcode_apa_secondary" data-apa-remove-row><?php esc_html_e('− Retirer la ligne', 'plugin-apa-agadev'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-apa-repeater-template>
                <div class="acl_shortcode_card acl_shortcode_apa_repeater_row" data-apa-repeater-row data-apa-row-index="__INDEX__">
                    <?php $render_fields($child_fields, $name . '[__INDEX__]', []); ?>
                    <button type="button" class="acl_shortcode_btn acl_shortcode_button_button acl_shortcode_apa_secondary" data-apa-remove-row><?php esc_html_e('− Retirer la ligne', 'plugin-apa-agadev'); ?></button>
                </div>
            </template>
            <button type="button" class="acl_shortcode_btn acl_shortcode_button_button acl_shortcode_apa_secondary" data-apa-add-row><?php esc_html_e('+ Ajouter une ligne', 'plugin-apa-agadev'); ?></button>
        </fieldset>
        <?php
    }
};

$render_benefits = static function (
    array $benefits,
    string $prefix,
    array $values
) use ($render_fields): void {
    // Keep benefit cards in one layout container without changing their payload paths.
    ?>
    <div class="acl_shortcode_apa_benefits">
    <?php
    foreach ($benefits as $benefit_key => $benefit) {
        if (! is_string($benefit_key) || ! is_array($benefit)) {
            continue;
        }
        ?>
        <div class="acl_shortcode_card acl_shortcode_apa_benefit">
            <h4><?php echo esc_html((string) ($benefit['name'] ?? $benefit_key)); ?></h4>
            <?php
            $benefit_values = is_array($values[$benefit_key] ?? null) ? $values[$benefit_key] : [];
            $render_fields(
                is_array($benefit['fields'] ?? null) ? $benefit['fields'] : [],
                $prefix . '[' . $benefit_key . ']',
                $benefit_values
            );
            ?>
        </div>
        <?php
    }
    ?>
    </div>
    <?php
};
?>

<div class="acl_shortcode_page acl_shortcode_main<?php echo $is_modal_layout ? ' acl_shortcode_apa_page--modal' : ''; ?>">
<div class="acl_shortcode_apa_portal<?php echo $is_modal_layout ? ' acl_shortcode_apa_portal--modal' : ''; ?> acl_shortcode_div" data-apa-portal>
    <?php if (! $is_modal_layout) : ?>
    <aside class="acl_shortcode_apa_sidebar acl_shortcode_div" aria-label="<?php esc_attr_e('Progression de la demande APA', 'plugin-apa-agadev'); ?>">
        <div class="acl_shortcode_apa_intro acl_shortcode_div">
            <h1 class="acl_shortcode_title acl_shortcode_h1"><?php esc_html_e('Portail de Conformité :', 'plugin-apa-agadev'); ?></h1>
            <h2 class="acl_shortcode_h2"><?php esc_html_e('Accès au Patrimoine Naturel et Savoirs du Gabon', 'plugin-apa-agadev'); ?></h2>
            <p class="acl_shortcode_p"><?php esc_html_e('Valorisez vos projets avec l’excellence de la biodiversité gabonaise via MAIVOU. Ce formulaire sécurise vos innovations en garantissant une conformité réglementaire totale.', 'plugin-apa-agadev'); ?></p>
        </div>
        <ol class="acl_shortcode_apa_progress" data-apa-step-navigation>
            <li class="acl_shortcode_apa_progress_item is-complete">
                <span class="acl_shortcode_apa_progress_number">0</span>
                <span><?php esc_html_e('Personne physique', 'plugin-apa-agadev'); ?></span>
            </li>
            <li class="acl_shortcode_apa_progress_item is-complete">
                <span class="acl_shortcode_apa_progress_number">1</span>
                <span><?php esc_html_e('Identification de l’utilisateur', 'plugin-apa-agadev'); ?></span>
            </li>
            <?php foreach ($section_groups as $section_index => $section_group) : ?>
                <li class="acl_shortcode_apa_progress_item<?php echo 0 === $section_index ? ' is-active' : ''; ?>" data-apa-section-nav-index="<?php echo esc_attr((string) $section_index); ?>"<?php echo 0 === $section_index ? ' aria-current="step"' : ''; ?>>
                    <span class="acl_shortcode_apa_progress_number"><?php echo esc_html((string) ($section_index + 2)); ?></span>
                    <span><?php echo esc_html((string) $section_group['title']); ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </aside>
    <?php endif; ?>

<article class="acl_shortcode_form acl_shortcode_article acl_shortcode_apa_form<?php echo $is_modal_layout ? ' acl_shortcode_apa_form--modal' : ''; ?><?php echo $submission_succeeded ? ' acl_shortcode_apa_form--success' : ''; ?>">
    <?php if ($submission_succeeded) : ?>
        <div class="acl_shortcode_notice acl_shortcode_notice--success acl_shortcode_div" role="status">
            <?php esc_html_e('Votre demande APA a bien été transmise à Maivou.', 'plugin-apa-agadev'); ?>
        </div>
    <?php elseif (is_array($submission)) : ?>
        <div class="acl_shortcode_notice acl_shortcode_notice--error acl_shortcode_div" role="alert">
            <p><?php echo esc_html((string) ($submission['error'] ?? __('La demande APA n’a pas pu être créée.', 'plugin-apa-agadev'))); ?></p>
            <?php $api_errors = is_array($submission['data']['errors'] ?? null) ? $submission['data']['errors'] : []; ?>
            <?php if ($api_errors !== []) : ?>
                <ul>
                    <?php foreach ($api_errors as $messages) : ?>
                        <?php foreach ((array) $messages as $message) : ?><li><?php echo esc_html((string) $message); ?></li><?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (! $submission_succeeded && $steps === []) : ?>
        <div class="acl_shortcode_empty acl_shortcode_div"><?php esc_html_e('Aucun formulaire APA disponible.', 'plugin-apa-agadev'); ?></div>
    <?php elseif (! $submission_succeeded) : ?>
        <form method="post" enctype="multipart/form-data" class="acl_shortcode_sections acl_shortcode_div" data-apa-step-form>
            <?php wp_nonce_field('apa_agadev_create_agreement', 'apa_agadev_nonce'); ?>
            <input type="hidden" name="apa_agadev_action" value="create_agreement">
            <div class="screen-reader-text" data-apa-form-status aria-live="polite"></div>

            <?php foreach ($steps as $step_index => $step) : ?>
                <?php
                $section_key = (string) $step['section_key'];
                $section_values = is_array($submitted[$section_key] ?? null) ? $submitted[$section_key] : [];
                $section_index = (int) $step['section_index'];
                $is_first_step = 0 === $step_index;
                $is_last_data_step = $step_index === $step_count - 1;
                ?>
                <section class="acl_shortcode_section acl_shortcode_apa_step" data-apa-step data-apa-step-index="<?php echo esc_attr((string) $step_index); ?>" data-apa-section-index="<?php echo esc_attr((string) $section_index); ?>"<?php echo $is_first_step ? '' : ' hidden'; ?>>
                    <div class="acl_shortcode_apa_hierarchy acl_shortcode_div">
                        <div class="acl_shortcode_apa_section_meta acl_shortcode_div">
                            <span class="acl_shortcode_apa_section_count acl_shortcode_span"><?php echo esc_html(sprintf(__('Section %1$d sur %2$d', 'plugin-apa-agadev'), $section_index + 1, $section_count)); ?></span>
                            <strong class="acl_shortcode_apa_section_title"><?php echo esc_html((string) $step['title']); ?></strong>
                        </div>
                        <div class="acl_shortcode_apa_section_track" aria-hidden="true">
                            <?php foreach ($section_groups as $track_index => $section_group) : ?>
                                <span class="acl_shortcode_apa_section_segment<?php echo $track_index < $section_index ? ' is-complete' : ($track_index === $section_index ? ' is-active' : ''); ?>"></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="acl_shortcode_apa_step_heading acl_shortcode_div">
                        <span class="acl_shortcode_apa_step_number acl_shortcode_span" aria-hidden="true"><?php echo esc_html((string) ($section_index + 1)); ?></span>
                        <div class="acl_shortcode_div">
                            <span class="screen-reader-text"><?php echo esc_html(sprintf(__('Section %1$d sur %2$d', 'plugin-apa-agadev'), $section_index + 1, $section_count)); ?></span>
                            <h2 class="acl_shortcode_title acl_shortcode_h2"><?php echo esc_html((string) $step['title']); ?></h2>
                            <?php if ('' !== $step['description']) : ?><div class="acl_shortcode_subtitle acl_shortcode_div"><?php echo esc_html((string) $step['description']); ?></div><?php endif; ?>
                        </div>
                    </div>

                    <?php if ($step['fields'] !== []) : ?>
                        <div class="acl_shortcode_apa_section_fields acl_shortcode_div" data-apa-review-group data-apa-review-group-title="<?php echo esc_attr((string) $step['title']); ?>">
                            <?php $render_fields($step['fields'], 'agreement[' . $section_key . ']', $section_values); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($step['subsections'] !== []) : ?>
                    <div class="acl_shortcode_apa_subsections acl_shortcode_div">
                        <?php foreach ($step['subsections'] as $subsection) : ?>
                            <?php
                            $subsection_key = (string) $subsection['key'];
                            $subsection_values = is_array($section_values[$subsection_key] ?? null) ? $section_values[$subsection_key] : [];
                            ?>
                            <section class="acl_shortcode_apa_subsection acl_shortcode_div" data-apa-review-group data-apa-review-group-title="<?php echo esc_attr((string) $subsection['title']); ?>">
                                <h3 class="acl_shortcode_title acl_shortcode_h3"><?php echo esc_html((string) $subsection['title']); ?></h3>
                                <?php if ('' !== $subsection['description']) : ?><div class="acl_shortcode_subtitle acl_shortcode_div"><?php echo esc_html((string) $subsection['description']); ?></div><?php endif; ?>
                                <?php $render_fields($subsection['fields'], 'agreement[' . $section_key . ']', $section_values); ?>
                                <?php if ($subsection['benefits'] !== []) : ?>
                                    <?php $render_benefits($subsection['benefits'], 'agreement[' . $section_key . '][' . $subsection_key . ']', $subsection_values); ?>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="acl_shortcode_actions acl_shortcode_apa_step_actions acl_shortcode_div">
                        <?php if (! $is_first_step) : ?>
                            <button type="button" class="acl_shortcode_btn acl_shortcode_button_button" data-apa-step-previous><?php esc_html_e('Retour', 'plugin-apa-agadev'); ?></button>
                        <?php endif; ?>
                        <button type="button" class="acl_shortcode_submit acl_shortcode_button_submit" data-apa-step-next><?php echo esc_html($is_last_data_step ? __('Vérifier la demande', 'plugin-apa-agadev') : __('Continuer', 'plugin-apa-agadev')); ?></button>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="acl_shortcode_section acl_shortcode_apa_step acl_shortcode_apa_review" data-apa-step data-apa-review-step data-apa-step-index="<?php echo esc_attr((string) $step_count); ?>" data-apa-section-index="<?php echo esc_attr((string) $section_count); ?>" hidden>
                <div class="acl_shortcode_apa_hierarchy acl_shortcode_div">
                    <div class="acl_shortcode_apa_section_meta acl_shortcode_div">
                        <span class="acl_shortcode_apa_section_count acl_shortcode_span"><?php esc_html_e('Vérification finale', 'plugin-apa-agadev'); ?></span>
                        <strong class="acl_shortcode_apa_section_title"><?php esc_html_e('Toutes les sections sont terminées', 'plugin-apa-agadev'); ?></strong>
                    </div>
                    <div class="acl_shortcode_apa_section_track" aria-hidden="true">
                        <?php foreach ($section_groups as $section_group) : ?>
                            <span class="acl_shortcode_apa_section_segment is-complete"></span>
                        <?php endforeach; ?>
                    </div>
                    <span class="acl_shortcode_apa_substep_count acl_shortcode_span"><?php esc_html_e('Relisez vos réponses avant leur transmission définitive.', 'plugin-apa-agadev'); ?></span>
                </div>
                <div class="acl_shortcode_apa_step_heading acl_shortcode_div">
                    <span class="acl_shortcode_apa_step_number acl_shortcode_span" aria-hidden="true">✓</span>
                    <div class="acl_shortcode_div">
                        <h2 class="acl_shortcode_title acl_shortcode_h2"><?php esc_html_e('Vérification et transmission', 'plugin-apa-agadev'); ?></h2>
                        <div class="acl_shortcode_subtitle acl_shortcode_div"><?php esc_html_e('Vérifiez les informations renseignées dans chaque section.', 'plugin-apa-agadev'); ?></div>
                    </div>
                </div>
                <div class="acl_shortcode_apa_review_summary" data-apa-review-summary></div>
                <div class="acl_shortcode_actions acl_shortcode_apa_step_actions acl_shortcode_div">
                    <button type="button" class="acl_shortcode_btn acl_shortcode_button_button" data-apa-step-previous><?php esc_html_e('Modifier mes réponses', 'plugin-apa-agadev'); ?></button>
                    <button type="submit" class="acl_shortcode_submit acl_shortcode_button_submit"><?php esc_html_e('Transmettre la demande APA', 'plugin-apa-agadev'); ?></button>
                </div>
            </section>
        </form>
    <?php endif; ?>
</article>
</div>
</div>
