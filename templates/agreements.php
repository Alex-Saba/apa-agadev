<?php
/**
 * Agreement section embedded in the existing home-user tab.
 *
 * @var array<int, mixed> $agreements
 * @var string $form_html
 * @var bool $submission_success
 * @var bool $open_modal
 */

if (! defined('ABSPATH')) {
    exit;
}

$modal_id = wp_unique_id('apa-agadev-agreement-modal-');
$status_labels = [
    'draft' => __('Brouillon', 'plugin-apa-agadev'),
    'submitted' => __('Soumis', 'plugin-apa-agadev'),
    'under_review' => __('En cours d’examen', 'plugin-apa-agadev'),
    'in_review' => __('En cours d’examen', 'plugin-apa-agadev'),
    'approved' => __('Approuvé', 'plugin-apa-agadev'),
    'rejected' => __('Rejeté', 'plugin-apa-agadev'),
    'completed' => __('Terminé', 'plugin-apa-agadev'),
    'done' => __('Terminé', 'plugin-apa-agadev'),
    'cancelled' => __('Annulé', 'plugin-apa-agadev'),
];
$format_date = static function ($value): string {
    if (! is_string($value) || '' === trim($value)) {
        return '';
    }

    $timestamp = strtotime($value);

    return false === $timestamp ? '' : wp_date((string) get_option('date_format'), $timestamp);
};
?>

<section class="acl_shortcode_agreements acl_shortcode_div" data-apa-agreements>
    <header class="acl_shortcode_agreements_header acl_shortcode_div">
        <div class="acl_shortcode_div">
            <h2 class="acl_shortcode_title acl_shortcode_h2"><?php esc_html_e('Mes agréments', 'plugin-apa-agadev'); ?></h2>
            <p class="acl_shortcode_subtitle acl_shortcode_p"><?php esc_html_e('Consultez vos demandes ou démarrez un nouvel agrément APA.', 'plugin-apa-agadev'); ?></p>
        </div>
        <button
            type="button"
            class="acl_shortcode_submit acl_shortcode_button_submit"
            data-apa-modal-open
            aria-controls="<?php echo esc_attr($modal_id); ?>"
            aria-haspopup="dialog"
        >
            <?php esc_html_e('Ajouter un agrément', 'plugin-apa-agadev'); ?>
        </button>
    </header>

    <?php if ($submission_success) : ?>
        <div class="acl_shortcode_notice acl_shortcode_notice--success acl_shortcode_div" role="status">
            <?php esc_html_e('Votre demande APA a bien été transmise à Maivou.', 'plugin-apa-agadev'); ?>
        </div>
    <?php endif; ?>

    <?php if ([] === $agreements) : ?>
        <div class="acl_shortcode_empty acl_shortcode_agreements_empty acl_shortcode_div">
            <h3 class="acl_shortcode_h3"><?php esc_html_e('Aucun agrément', 'plugin-apa-agadev'); ?></h3>
            <p class="acl_shortcode_p"><?php esc_html_e('Vous n’avez pas encore créé de demande d’agrément APA.', 'plugin-apa-agadev'); ?></p>
        </div>
    <?php else : ?>
        <div class="acl_shortcode_agreements_table_wrap acl_shortcode_div">
            <table class="acl_shortcode_agreements_table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Agrément', 'plugin-apa-agadev'); ?></th>
                        <th scope="col"><?php esc_html_e('Titulaire', 'plugin-apa-agadev'); ?></th>
                        <th scope="col"><?php esc_html_e('Statut', 'plugin-apa-agadev'); ?></th>
                        <th scope="col"><?php esc_html_e('Créé le', 'plugin-apa-agadev'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agreements as $agreement) : ?>
                        <?php
                        if (! is_array($agreement)) {
                            continue;
                        }

                        $user = is_array($agreement['user'] ?? null) ? $agreement['user'] : [];
                        $holder = trim((string) ($user['firstname'] ?? '') . ' ' . (string) ($user['lastname'] ?? ''));
                        $code = trim((string) ($agreement['code'] ?? ''));
                        $status = trim((string) ($agreement['status'] ?? ''));
                        $status_label = $status_labels[$status] ?? $status;
                        $created_at = $format_date($agreement['created_at'] ?? null);
                        ?>
                        <tr>
                            <td data-label="<?php esc_attr_e('Agrément', 'plugin-apa-agadev'); ?>"><?php echo esc_html('' !== $code ? $code : __('Agrément APA', 'plugin-apa-agadev')); ?></td>
                            <td data-label="<?php esc_attr_e('Titulaire', 'plugin-apa-agadev'); ?>"><?php echo esc_html('' !== $holder ? $holder : '—'); ?></td>
                            <td data-label="<?php esc_attr_e('Statut', 'plugin-apa-agadev'); ?>"><?php echo esc_html('' !== $status_label ? $status_label : '—'); ?></td>
                            <td data-label="<?php esc_attr_e('Créé le', 'plugin-apa-agadev'); ?>"><?php echo esc_html('' !== $created_at ? $created_at : '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div
        id="<?php echo esc_attr($modal_id); ?>"
        class="acl_shortcode_apa_modal acl_shortcode_div"
        data-apa-modal
        <?php echo $open_modal ? 'data-apa-modal-auto-open' : 'hidden'; ?>
    >
        <div class="acl_shortcode_apa_modal_backdrop acl_shortcode_div" data-apa-modal-close></div>
        <div
            class="acl_shortcode_apa_modal_dialog acl_shortcode_div"
            role="dialog"
            aria-modal="true"
            aria-labelledby="<?php echo esc_attr($modal_id . '-title'); ?>"
            tabindex="-1"
        >
            <header class="acl_shortcode_apa_modal_header acl_shortcode_div">
                <h2 id="<?php echo esc_attr($modal_id . '-title'); ?>" class="acl_shortcode_title acl_shortcode_h2"><?php esc_html_e('Nouvel agrément APA', 'plugin-apa-agadev'); ?></h2>
                <button type="button" class="acl_shortcode_apa_modal_close" data-apa-modal-close aria-label="<?php esc_attr_e('Fermer le formulaire', 'plugin-apa-agadev'); ?>">&times;</button>
            </header>
            <div class="acl_shortcode_apa_modal_body acl_shortcode_div">
                <?php
                // The form is rendered by this plugin and escapes its dynamic values.
                echo $form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        </div>
    </div>
</section>
