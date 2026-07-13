<?php
/**
 * Interactive APA agreement form generated from Maivou's filtered catalog.
 *
 * @var array<string, mixed> $catalog
 * @var array<string, mixed> $remote_options
 * @var array<string, mixed> $submitted
 * @var array<string, mixed>|null $submission
 */

if (! defined('ABSPATH')) {
    exit;
}

$sections = is_array($catalog['sections'] ?? null) ? $catalog['sections'] : [];

$is_list = static function (array $value): bool {
    return $value !== [] && array_keys($value) === range(0, count($value) - 1);
};

$field_options = static function (array $field) use ($remote_options): array {
    if (is_array($field['options'] ?? null)) {
        return $field['options'];
    }

    $endpoint = isset($field['optionsEndpoint']) ? (string) $field['optionsEndpoint'] : '';
    $items = is_array($remote_options[$endpoint] ?? null) ? $remote_options[$endpoint] : [];
    $options = [];

    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }

        $value = $item['code'] ?? $item['uuid'] ?? $item['id'] ?? null;
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
    ?>
    <div class="apa-agadev__field">
        <?php if ('checkbox' !== $type) : ?>
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required) : ?><span aria-hidden="true"> *</span><?php endif; ?>
            </label>
        <?php endif; ?>

        <?php if ('textarea' === $type) : ?>
            <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>"<?php echo $required ? ' required' : ''; ?>><?php echo esc_textarea((string) $value); ?></textarea>
        <?php elseif ('select' === $type || 'multiselect' === $type) : ?>
            <?php $selected_values = is_array($value) ? array_map('strval', $value) : [(string) $value]; ?>
            <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name . ('multiselect' === $type ? '[]' : '')); ?>"<?php echo 'multiselect' === $type ? ' multiple' : ''; ?><?php echo $required ? ' required' : ''; ?>>
                <?php if ('select' === $type) : ?><option value=""><?php esc_html_e('Sélectionner', 'plugin-apa-agadev'); ?></option><?php endif; ?>
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr((string) $option_value); ?>"<?php echo in_array((string) $option_value, $selected_values, true) ? ' selected' : ''; ?>><?php echo esc_html((string) $option_label); ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ('radio' === $type) : ?>
            <fieldset>
                <legend class="screen-reader-text"><?php echo esc_html($label); ?></legend>
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <label class="apa-agadev__choice">
                        <input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $option_value); ?>"<?php checked((string) $value, (string) $option_value); ?><?php echo $required ? ' required' : ''; ?>>
                        <?php echo esc_html((string) $option_label); ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php elseif ('checkbox' === $type) : ?>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="0">
            <label class="apa-agadev__choice" for="<?php echo esc_attr($id); ?>">
                <input id="<?php echo esc_attr($id); ?>" type="checkbox" name="<?php echo esc_attr($name); ?>" value="1"<?php checked(in_array($value, [true, 1, '1'], true)); ?><?php echo $required ? ' required' : ''; ?>>
                <?php echo esc_html($label); ?><?php if ($required) : ?><span aria-hidden="true"> *</span><?php endif; ?>
            </label>
        <?php elseif (in_array($type, ['file', 'dropzone'], true)) : ?>
            <p class="apa-agadev__unavailable"><?php esc_html_e('Le téléversement de ce document n’est pas encore exposé par l’API Maivou.', 'plugin-apa-agadev'); ?></p>
        <?php else : ?>
            <?php $html_type = in_array($type, ['date', 'email', 'number'], true) ? $type : 'text'; ?>
            <input id="<?php echo esc_attr($id); ?>" type="<?php echo esc_attr($html_type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>"<?php echo $required ? ' required' : ''; ?>>
        <?php endif; ?>
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
        <fieldset class="apa-agadev__repeater" data-apa-repeater>
            <legend><?php echo esc_html((string) ($field['label'] ?? $key)); ?><?php if (! empty($field['required'])) : ?><span aria-hidden="true"> *</span><?php endif; ?></legend>
            <div data-apa-repeater-rows>
                <?php foreach ($rows as $index => $row) : ?>
                    <div class="apa-agadev__repeater-row" data-apa-repeater-row data-apa-row-index="<?php echo esc_attr((string) $index); ?>">
                        <?php $render_fields($child_fields, $name . '[' . (string) $index . ']', is_array($row) ? $row : []); ?>
                        <button type="button" class="apa-agadev__secondary" data-apa-remove-row><?php esc_html_e('Retirer cette ligne', 'plugin-apa-agadev'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-apa-repeater-template>
                <div class="apa-agadev__repeater-row" data-apa-repeater-row data-apa-row-index="__INDEX__">
                    <?php $render_fields($child_fields, $name . '[__INDEX__]', []); ?>
                    <button type="button" class="apa-agadev__secondary" data-apa-remove-row><?php esc_html_e('Retirer cette ligne', 'plugin-apa-agadev'); ?></button>
                </div>
            </template>
            <button type="button" class="apa-agadev__secondary" data-apa-add-row><?php esc_html_e('Ajouter une ligne', 'plugin-apa-agadev'); ?></button>
        </fieldset>
        <?php
    }
};

$render_benefits = static function (
    array $benefits,
    string $prefix,
    array $values
) use ($render_fields): void {
    foreach ($benefits as $benefit_key => $benefit) {
        if (! is_string($benefit_key) || ! is_array($benefit)) {
            continue;
        }
        ?>
        <div class="apa-agadev__benefit">
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
};
?>

<div class="apa-agadev apa-agadev--form">
    <?php if (is_array($submission) && ! empty($submission['ok'])) : ?>
        <div class="apa-agadev__notice apa-agadev__notice--success" role="status">
            <?php esc_html_e('Votre demande APA a bien été transmise à Maivou.', 'plugin-apa-agadev'); ?>
        </div>
    <?php elseif (is_array($submission)) : ?>
        <div class="apa-agadev__notice apa-agadev__notice--error" role="alert">
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

    <?php if ($sections === []) : ?>
        <p><?php esc_html_e('Aucun formulaire APA disponible.', 'plugin-apa-agadev'); ?></p>
    <?php else : ?>
        <form method="post" class="apa-agadev__form">
            <?php wp_nonce_field('apa_agadev_create_agreement', 'apa_agadev_nonce'); ?>
            <input type="hidden" name="apa_agadev_action" value="create_agreement">

            <?php foreach ($sections as $section_key => $section) : ?>
                <?php
                if (! is_string($section_key) || ! is_array($section)) {
                    continue;
                }

                $section_values = is_array($submitted[$section_key] ?? null) ? $submitted[$section_key] : [];
                ?>
                <section class="apa-agadev__section">
                    <h2><?php echo esc_html((string) ($section['title'] ?? $section_key)); ?></h2>
                    <?php if (! empty($section['description'])) : ?><p><?php echo esc_html((string) $section['description']); ?></p><?php endif; ?>

                    <?php $render_fields(is_array($section['fields'] ?? null) ? $section['fields'] : [], 'agreement[' . $section_key . ']', $section_values); ?>

                    <?php foreach ((array) ($section['subsections'] ?? []) as $subsection_key => $subsection) : ?>
                        <?php if (! is_string($subsection_key) || ! is_array($subsection)) { continue; } ?>
                        <div class="apa-agadev__subsection">
                            <h3><?php echo esc_html((string) ($subsection['title'] ?? $subsection_key)); ?></h3>
                            <?php if (! empty($subsection['description'])) : ?><p><?php echo esc_html((string) $subsection['description']); ?></p><?php endif; ?>
                            <?php $render_fields(is_array($subsection['fields'] ?? null) ? $subsection['fields'] : [], 'agreement[' . $section_key . ']', $section_values); ?>
                            <?php if (is_array($subsection['benefits'] ?? null)) : ?>
                                <?php $render_benefits($subsection['benefits'], 'agreement[' . $section_key . '][' . $subsection_key . ']', is_array($section_values[$subsection_key] ?? null) ? $section_values[$subsection_key] : []); ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>

            <button type="submit" class="apa-agadev__submit"><?php esc_html_e('Envoyer la demande APA', 'plugin-apa-agadev'); ?></button>
        </form>
    <?php endif; ?>
</div>
