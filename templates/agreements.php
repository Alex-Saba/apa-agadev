<?php
/**
 * Agreement list.
 *
 * @var array<int, mixed> $agreements
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<div class="apa-agadev apa-agadev--agreements">
    <?php if ($agreements === []) : ?>
        <p><?php esc_html_e('Aucun accord APA disponible.', 'plugin-apa-agadev'); ?></p>
    <?php else : ?>
        <div class="apa-agadev__grid">
            <?php foreach ($agreements as $agreement) : ?>
                <?php
                if (! is_array($agreement)) {
                    continue;
                }

                $user = is_array($agreement['user'] ?? null) ? $agreement['user'] : [];
                $holder = trim((string) ($user['firstname'] ?? '') . ' ' . (string) ($user['lastname'] ?? ''));
                ?>
                <article class="apa-agadev__card">
                    <h3><?php echo esc_html((string) ($agreement['code'] ?? $agreement['uuid'] ?? __('Accord APA', 'plugin-apa-agadev'))); ?></h3>
                    <p><strong><?php esc_html_e('Statut :', 'plugin-apa-agadev'); ?></strong> <?php echo esc_html((string) ($agreement['status'] ?? '')); ?></p>
                    <?php if ('' !== $holder) : ?>
                        <p><strong><?php esc_html_e('Titulaire :', 'plugin-apa-agadev'); ?></strong> <?php echo esc_html($holder); ?></p>
                    <?php endif; ?>
                    <?php if (! empty($agreement['created_at'])) : ?>
                        <p><strong><?php esc_html_e('Créé le :', 'plugin-apa-agadev'); ?></strong> <?php echo esc_html((string) $agreement['created_at']); ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
