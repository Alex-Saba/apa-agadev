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
            [
                'code' => '[apa_agadev_public_lots]',
                'description' => __('Affiche uniquement les lots visibles dont le statut Maivou est ready.', 'plugin-apa-agadev'),
            ],
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('APA Agadev', 'plugin-apa-agadev'); ?></h1>
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
                <li><?php esc_html_e('La liste publique affiche exclusivement les lots au statut ready.', 'plugin-apa-agadev'); ?></li>
            </ul>
        </div>
        <?php
    }
}
