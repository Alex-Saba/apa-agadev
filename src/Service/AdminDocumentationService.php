<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

/**
 * Exposes the APA shortcode documentation in the WordPress back office.
 */
final class AdminDocumentationService
{
    /**
     * Registers the documentation page under the WordPress Settings menu.
     */
    public function registerAdminMenu(): void
    {
        add_options_page(
            __('APA Agadev', 'plugin-apa-agadev'),
            __('APA Agadev', 'plugin-apa-agadev'),
            'manage_options',
            'apa-agadev-documentation',
            [$this, 'renderPage']
        );
    }

    /**
     * Renders the shortcode reference for administrators.
     */
    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $last_sync = (int) get_option(LotSyncService::OPTION_LAST_SYNC, 0);
        $last_result = get_option(LotSyncService::OPTION_LAST_RESULT, []);
        $last_result = is_array($last_result) ? $last_result : [];
        $sync_state = isset($_GET['apa_lot_sync'])
            ? sanitize_key(wp_unslash((string) $_GET['apa_lot_sync']))
            : '';

        $shortcodes = [
            [
                'code' => '[apa_agadev_form]',
                'description' => __('Affiche le formulaire APA filtré pour l’utilisateur connecté et transmet la demande à Maivou.', 'plugin-apa-agadev'),
            ],
            [
                'code' => '[apa_agadev_form role="chercheur"]',
                'description' => __('Demande le formulaire correspondant au rôle indiqué, sous réserve des autorisations accordées par Maivou.', 'plugin-apa-agadev'),
            ],
            [
                'code' => '[apa_agadev_agreements]',
                'description' => __('Affiche les accords APA visibles par l’utilisateur connecté.', 'plugin-apa-agadev'),
            ],
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('APA Agadev', 'plugin-apa-agadev'); ?></h1>

            <?php if (in_array($sync_state, ['success', 'error'], true)) : ?>
                <div class="notice notice-<?php echo esc_attr($sync_state); ?> is-dismissible">
                    <p><?php echo esc_html((string) ($last_result['message'] ?? __('Synchronisation terminée.', 'plugin-apa-agadev'))); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Synchronisation des lots finalisés', 'plugin-apa-agadev'); ?></h2>
            <p>
                <?php esc_html_e('Le cron horaire conserve localement les lots vendus ou annulés ayant une request associée.', 'plugin-apa-agadev'); ?>
            </p>
            <table class="widefat striped" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Dernière synchronisation', 'plugin-apa-agadev'); ?></th>
                        <td>
                            <?php echo esc_html($last_sync > 0 ? date_i18n('d/m/Y H:i:s', $last_sync) : __('Jamais', 'plugin-apa-agadev')); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Lots synchronisés', 'plugin-apa-agadev'); ?></th>
                        <td><?php echo esc_html((string) ((int) ($last_result['synced'] ?? 0))); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Erreurs', 'plugin-apa-agadev'); ?></th>
                        <td><?php echo esc_html((string) ((int) ($last_result['errors'] ?? 0))); ?></td>
                    </tr>
                    <?php if (! empty($last_result['message'])) : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Résultat', 'plugin-apa-agadev'); ?></th>
                            <td><?php echo esc_html((string) $last_result['message']); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(LotSyncService::ADMIN_ACTION); ?>">
                <?php wp_nonce_field(LotSyncService::NONCE_ACTION); ?>
                <?php submit_button(__('Synchroniser maintenant', 'plugin-apa-agadev'), 'secondary'); ?>
            </form>

            <h2><?php esc_html_e('Fiches publiques de lots', 'plugin-apa-agadev'); ?></h2>
            <p>
                <?php esc_html_e('Chaque lot synchronisé possède une fiche individuelle. Il n’existe aucune page de liste ou d’archive publique.', 'plugin-apa-agadev'); ?>
            </p>
            <p><code><?php echo esc_html(home_url('/lot/{code}/')); ?></code></p>

            <h2><?php esc_html_e('Documentation des shortcodes', 'plugin-apa-agadev'); ?></h2>
            <p>
                <?php esc_html_e('Copiez le shortcode souhaité dans une page, un article ou un bloc Shortcode WordPress.', 'plugin-apa-agadev'); ?>
            </p>

            <table class="widefat striped" role="presentation">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Shortcode', 'plugin-apa-agadev'); ?></th>
                        <th scope="col"><?php esc_html_e('Fonctionnement', 'plugin-apa-agadev'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shortcodes as $shortcode) : ?>
                        <tr>
                            <td><code><?php echo esc_html($shortcode['code']); ?></code></td>
                            <td><?php echo esc_html($shortcode['description']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Conditions de fonctionnement', 'plugin-apa-agadev'); ?></h2>
            <ul class="ul-disc">
                <li><?php esc_html_e('ACL WordPress API Bridge doit être installé, activé et configuré.', 'plugin-apa-agadev'); ?></li>
                <li><?php esc_html_e('Les données affichées dépendent du rôle, des scopes et des règles de visibilité Maivou de l’utilisateur connecté.', 'plugin-apa-agadev'); ?></li>
                <li><?php esc_html_e('Une erreur HTTP 403 indique que Maivou refuse l’accès avec les autorisations actuelles.', 'plugin-apa-agadev'); ?></li>
                <li><?php esc_html_e('La synchronisation machine nécessite le scope lots.read et ne dépend d’aucun utilisateur connecté.', 'plugin-apa-agadev'); ?></li>
                <li><?php esc_html_e('Seuls les lots vendus ou annulés ayant une request associée disposent d’une fiche publique.', 'plugin-apa-agadev'); ?></li>
            </ul>
        </div>
        <?php
    }
}
