<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

final class OrderShortcodeService
{
    private OrderDataService $data;
    private ?array $submission = null;

    public function __construct(?OrderDataService $data = null)
    {
        $this->data = $data ?? new OrderDataService();
    }

    public function register(): void
    {
        add_shortcode('apa_agadev_orders', [$this, 'render']);
        add_shortcode('apa_agadev_order_form', fn () => $this->renderForm());
        add_action('template_redirect', [$this, 'handlePost']);
    }

    private function input(array $source, string $key): string
    {
        return is_string($source[$key] ?? null) ? trim(wp_unslash($source[$key])) : '';
    }

    public function handlePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! isset($_POST['apa_order_intent'])) {
            return;
        }
        if (! is_user_logged_in() || ! wp_verify_nonce($this->input($_POST, '_apa_order_nonce'), 'apa_order_save')) {
            $this->submission = $this->data->error('Votre session a expiré. Rechargez la page.', 403);
            return;
        }
        $values = is_array($_POST['order'] ?? null) ? wp_unslash($_POST['order']) : [];
        $this->submission = $this->data->save($values, $this->input($_POST, 'order_uuid'), $this->input($_POST, 'apa_order_intent'));
        if ($this->submission['ok']) {
            $order = $this->data->entity($this->submission);
            $uuid = (string) ($order['uuid'] ?? '');
            if (! $this->data->validUuid($uuid)) {
                $this->submission = $this->data->error('Réponse d’enregistrement invalide.', 502);
                return;
            }
            // Email failure must never turn a confirmed submission into a failed order.
            try {
                (new OrderConfirmationService())->send($this->submission, $this->input($_POST, 'apa_order_intent'));
            } catch (\Throwable $error) {
                error_log('[APA Agadev] Order confirmation could not run (' . get_class($error) . ')');
            }
            // Redirect after a successful POST so refreshing never creates another order.
            wp_safe_redirect($this->url(['order_uuid' => $uuid, 'order_saved' => '1']));
            exit;
        }
    }

    public function url(array $args = []): string
    {
        // Keep the account tab while dropping stale order-specific query parameters.
        if ($this->input($_GET, 'view') === 'commandes') {
            $args['view'] = 'commandes';
        }
        return add_query_arg($args, get_permalink());
    }

    public function label(string $status): string
    {
        return ['draft' => 'Brouillon', 'submitted' => 'Soumise', 'in_progress' => 'En cours', 'completed' => 'Complétée', 'delivered' => 'Livrée', 'cancelled' => 'Annulée'][$status] ?? $status;
    }

    private function error(array $response): string
    {
        if (($response['reason'] ?? '') === 'order_read_required') {
            return '<div class="acl_shortcode_notice acl_shortcode_notice--error" role="alert">' . esc_html($response['error']) . '</div>';
        }
        if ((int) ($response['status'] ?? 0) === 403) {
            $remoteMessage = $response['data']['message'] ?? '';
            $message = $remoteMessage === 'Invalid scope(s) provided.'
                ? 'Votre session Maivou ne dispose pas des droits API nécessaires pour les commandes. Si vos permissions ont changé, déconnectez-vous puis reconnectez-vous.'
                : 'Votre compte Maivou n’est pas autorisé à accéder à cette commande ou à effectuer cette action. La consultation nécessite la permission order-read.';
            return '<div class="acl_shortcode_notice acl_shortcode_notice--error" role="alert">' . esc_html($message) . '</div>';
        }
        $message = $response['error'] ?? $response['data']['message'] ?? 'Impossible de charger les commandes.';
        $errors = $response['data']['errors'] ?? [];
        if (is_array($errors)) {
            foreach ($errors as $messages) {
                foreach ((array) $messages as $text) {
                    if (is_string($text)) {
                        $message .= ' ' . $text;
                    }
                }
            }
        }
        return '<div class="acl_shortcode_notice acl_shortcode_notice--error" role="alert">' . esc_html((string) $message) . '</div>';
    }

    public function render(): string
    {
        wp_enqueue_style('plugin-apa-agadev');
        if (! is_user_logged_in()) {
            return $this->error($this->data->error('Connectez-vous pour accéder à vos commandes.', 401));
        }
        wp_enqueue_script('plugin-apa-agadev');
        $modal = '';
        $modalTitle = 'Commande';
        $action = $this->input($_GET, 'order_action');
        $uuid = $this->input($_GET, 'order_uuid');
        if ($this->submission !== null || in_array($action, ['new', 'edit'], true)) {
            $modal = $this->renderForm();
            $modalTitle = $action === 'new' ? 'Ajouter une commande' : 'Modifier mon brouillon';
        } elseif ($uuid !== '') {
            if (! $this->data->validUuid($uuid)) {
                return $this->error($this->data->error('Référence de commande invalide.'));
            }
            $response = $this->data->ownOrder($uuid);
            if (! $response['ok']) {
                return $this->error($response);
            } else {
                $order = $this->data->entity($response);
                $modalTitle = 'Commande ' . (string) ($order['code'] ?? '');
                if ($this->input($_GET, 'order_saved') === '1') {
                    $modalTitle = ($order['status'] ?? '') === 'draft' ? 'Brouillon enregistré' : 'Commande soumise';
                    $modal = $this->template('order-form', ['savedOrder' => $order]);
                } else {
                    return $this->template('orders', [
                        'detail' => $this->template('order-detail', ['order' => $order]),
                    ]);
                }
            }
        }
        $page = max(1, (int) $this->input($_GET, 'order_page'));
        $response = $this->data->ownOrders($page, '');
        if (! $response['ok']) {
            return $this->error($response);
        }
        $payload = $response['data'] ?? [];
        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            return $this->error($this->data->error('Liste des commandes invalide.', 502));
        }
        $context = $this->data->request('/orders/context');
        return $this->template('orders', [
            'orders' => $payload['data'], 'page' => (int) $payload['meta']['current_page'],
            'modal' => $modal, 'modalTitle' => $modalTitle,
            'savedMessage' => $this->input($_GET, 'order_saved') === '1' ? (($order['status'] ?? '') === 'draft' ? 'Brouillon enregistré. Vous pourrez le reprendre depuis la liste.' : 'Commande enregistrée. Son statut est disponible dans la liste.') : '',
            'lastPage' => (int) ($payload['meta']['last_page'] ?? $payload['last_page'] ?? 1),
            'canCreate' => $context['ok'] && ($this->data->entity($context)['create'] ?? false) === true,
        ]);
    }

    public function renderForm(bool $standalone = true): string
    {
        wp_enqueue_style('plugin-apa-agadev');
        if (! is_user_logged_in()) {
            return $this->error($this->data->error('Connectez-vous pour accéder à vos commandes.', 401));
        }
        $identity = $this->data->buyerIdentity();
        if (! $identity['ok']) return $this->error($identity);
        // A standalone form shortcode also displays its saved order after redirect.
        if ($standalone && $this->submission === null && $this->input($_GET, 'order_saved') === '1') {
            return $this->render();
        }
        $uuid = $this->submission['saved_uuid'] ?? $this->input($_GET, 'order_uuid');
        if ($this->submission !== null && empty($this->submission['saved_uuid'])) {
            $uuid = $this->input($_POST, 'order_uuid');
        }
        $values = [];
        if ($uuid !== '') {
            if (! $this->data->validUuid($uuid)) {
                return $this->error($this->data->error('Référence de commande invalide.'));
            }
            $existing = $this->data->ownOrder($uuid);
            if (! $existing['ok']) {
                return $this->error($existing);
            }
            $values = $this->data->entity($existing);
            if (($values['status'] ?? '') !== 'draft' || ! in_array('update', $values['allowed_actions'] ?? [], true)) {
                return $this->error($this->data->error('Cette commande ne peut pas être modifiée.', 403));
            }
        } else {
            $context = $this->data->request('/orders/context');
            if (! $context['ok']) {
                return $this->error($context);
            }
            if (($this->data->entity($context)['create'] ?? false) !== true) {
                return $this->error($this->data->error('Vous ne pouvez pas créer de commande pour votre compte.', 403));
            }
        }
        $canSubmit = $uuid === '' || in_array('submit', $values['allowed_actions'] ?? [], true);
        if ($this->submission !== null && is_array($_POST['order'] ?? null)) {
            $values['meta'] = is_array($_POST['order']['meta'] ?? null) ? wp_unslash($_POST['order']['meta']) : [];
            foreach (['product_uuid', 'quantity', 'expected_delivery_date'] as $field) {
                $values[$field] = $this->input($_POST['order'], $field);
            }
        }
        $products = $this->data->products();
        if (! $products['ok']) {
            return $this->error($products);
        }
        return $this->template('order-form', [
            'uuid' => $uuid, 'values' => $values, 'products' => $products['data'], 'canSubmit' => $canSubmit,
            'error' => $this->submission !== null ? $this->error($this->submission) : '',
        ]);
    }

    private function template(string $name, array $variables): string
    {
        extract($variables, EXTR_SKIP);
        ob_start();
        require PLUGIN_APA_AGADEV_PATH . 'templates/' . $name . '.php';
        return (string) ob_get_clean();
    }
}
