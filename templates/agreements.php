<?php
/**
 * Agreement list.
 *
 * @var array<int, mixed> $agreements
 * @var string $agreements_error
 * @var string $form
 * @var bool $open_modal
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<?php
$status_labels = [
    'draft' => __('Brouillon', 'plugin-apa-agadev'),
    'pending' => __('En attente', 'plugin-apa-agadev'),
    'accepted' => __('Valide', 'plugin-apa-agadev'),
    'rejected' => __('Rejeté', 'plugin-apa-agadev'),
];
$modal_id = 'apa-agadev-agreement-modal';
$modal_return_url = (string) add_query_arg('view', 'agrements', get_permalink());
$agreement_items = array_values(array_filter($agreements, 'is_array'));
$per_page = 10;
$total_pages = max(1, (int) ceil(count($agreement_items) / $per_page));
$current_page = isset($_GET['agreement_page'])
    ? max(1, absint(wp_unslash((string) $_GET['agreement_page'])))
    : 1;
$current_page = min($current_page, $total_pages);
$visible_agreements = array_slice($agreement_items, ($current_page - 1) * $per_page, $per_page);
$page_url = static fn (int $page): string => (string) add_query_arg([
    'view' => 'agrements',
    'agreement_page' => $page,
], get_permalink());
$format_date = static function ($value): string {
    if (! is_string($value) || '' === trim($value)) {
        return '—';
    }

    $timestamp = strtotime($value);

    return false === $timestamp ? '—' : wp_date('d/m/Y', $timestamp);
};
?>

<section class="acl_shortcode_agreements acl_shortcode_div" data-apa-agreements>
    <header class="acl_shortcode_agreements_header acl_shortcode_div">
        <div class="acl_shortcode_div">
            <h2 class="acl_shortcode_agreements_title acl_shortcode_h2"><?php esc_html_e('Mes agréments', 'plugin-apa-agadev'); ?></h2>
        </div>
        <button type="button" class="acl_shortcode_agreements_add acl_shortcode_button_button" data-apa-agreement-modal-open aria-haspopup="dialog" aria-controls="<?php echo esc_attr($modal_id); ?>">
            <?php esc_html_e('Ajouter un agrément', 'plugin-apa-agadev'); ?>
        </button>
    </header>

    <?php if ('' !== $agreements_error) : ?>
        <div class="acl_shortcode_notice acl_shortcode_notice--error acl_shortcode_apa_error acl_shortcode_div" role="alert">
            <?php echo esc_html($agreements_error); ?>
        </div>
    <?php elseif ($agreement_items === []) : ?>
        <div class="acl_shortcode_agreements_empty acl_shortcode_div">
            <h3 class="acl_shortcode_h3"><?php esc_html_e('Aucun agrément pour le moment', 'plugin-apa-agadev'); ?></h3>
            <p class="acl_shortcode_p"><?php esc_html_e('Votre première demande apparaîtra ici après sa création.', 'plugin-apa-agadev'); ?></p>
        </div>
    <?php else : ?>
        <div class="acl_shortcode_agreements_table_wrap acl_shortcode_div">
            <table class="acl_shortcode_agreements_table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Nom de l’agrément', 'plugin-apa-agadev'); ?></th>
                        <th scope="col"><?php esc_html_e('Début de validité', 'plugin-apa-agadev'); ?></th>
                        <th scope="col"><?php esc_html_e('Fin de validité', 'plugin-apa-agadev'); ?></th>
                        <th scope="col"><?php esc_html_e('Statut', 'plugin-apa-agadev'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visible_agreements as $agreement) : ?>
                        <?php
                        $agreement_code = trim((string) ($agreement['code'] ?? ''));
                        $agreement_name = '' !== $agreement_code
                            ? $agreement_code
                            : __('Formulaire APA', 'plugin-apa-agadev');
                        $status = strtolower((string) ($agreement['status'] ?? ''));
                        $status_label = $status_labels[$status] ?? ('' !== $status ? ucfirst($status) : '—');
                        $status_class = $status;
                        $end_timestamp = isset($agreement['ends_at']) && is_string($agreement['ends_at'])
                            ? strtotime($agreement['ends_at'])
                            : false;

                        if ('accepted' === $status && false !== $end_timestamp && $end_timestamp < current_time('timestamp')) {
                            $status_label = __('Expiré', 'plugin-apa-agadev');
                            $status_class = 'expired';
                        } elseif ('accepted' === $status) {
                            $status_class = 'valid';
                        }
                        ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($agreement_name); ?></th>
                            <td><?php echo esc_html($format_date($agreement['starts_at'] ?? null)); ?></td>
                            <td><?php echo esc_html($format_date($agreement['ends_at'] ?? null)); ?></td>
                            <td><span class="acl_shortcode_agreements_status acl_shortcode_agreements_status--<?php echo esc_attr(sanitize_html_class($status_class ?: 'unknown')); ?>"><?php echo esc_html($status_label); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1) : ?>
            <nav class="acl_shortcode_agreements_pagination" aria-label="<?php esc_attr_e('Pagination des agréments', 'plugin-apa-agadev'); ?>">
                <?php if ($current_page > 1) : ?>
                    <a class="acl_shortcode_agreements_page acl_shortcode_agreements_page--arrow" href="<?php echo esc_url($page_url($current_page - 1)); ?>" aria-label="<?php esc_attr_e('Page précédente', 'plugin-apa-agadev'); ?>">&lsaquo;</a>
                <?php endif; ?>
                <?php for ($page = 1; $page <= $total_pages; $page++) : ?>
                    <a class="acl_shortcode_agreements_page<?php echo $page === $current_page ? ' is-current' : ''; ?>" href="<?php echo esc_url($page_url($page)); ?>"<?php echo $page === $current_page ? ' aria-current="page"' : ''; ?>><?php echo esc_html((string) $page); ?></a>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages) : ?>
                    <a class="acl_shortcode_agreements_page acl_shortcode_agreements_page--arrow" href="<?php echo esc_url($page_url($current_page + 1)); ?>" aria-label="<?php esc_attr_e('Page suivante', 'plugin-apa-agadev'); ?>">&rsaquo;</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<div id="<?php echo esc_attr($modal_id); ?>" class="acl_shortcode_agreement_modal<?php echo $open_modal ? ' is-open' : ''; ?> acl_shortcode_div" data-apa-agreement-modal data-apa-success-return-url="<?php echo esc_url($modal_return_url); ?>"<?php echo $open_modal ? ' data-apa-modal-initial-open="true"' : ' hidden'; ?>>
    <div class="acl_shortcode_agreement_modal_backdrop acl_shortcode_div" data-apa-agreement-modal-close></div>
    <div class="acl_shortcode_agreement_modal_dialog acl_shortcode_div" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($modal_id); ?>-title">
        <header class="acl_shortcode_agreement_modal_header acl_shortcode_div">
            <div class="acl_shortcode_div">
                <span class="acl_shortcode_agreement_modal_eyebrow"><?php esc_html_e('Demande APA', 'plugin-apa-agadev'); ?></span>
                <h2 id="<?php echo esc_attr($modal_id); ?>-title" class="acl_shortcode_agreement_modal_title acl_shortcode_h2"><?php esc_html_e('Ajouter un agrément', 'plugin-apa-agadev'); ?></h2>
            </div>
            <button type="button" class="acl_shortcode_agreement_modal_close acl_shortcode_button_button" data-apa-agreement-modal-close aria-label="<?php esc_attr_e('Fermer le formulaire', 'plugin-apa-agadev'); ?>">&times;</button>
        </header>
        <div class="acl_shortcode_agreement_modal_body acl_shortcode_div">
            <?php
            // The form is rendered and escaped by the plugin template itself.
            echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>
    </div>
</div>
