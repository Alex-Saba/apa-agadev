<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

/** Fixed order contract: no form catalog and no document transfer. */
final class OrderDataService
{
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public function request(string $endpoint, string $method = 'GET', array $body = [], array $params = []): array
    {
        $transport = $this->transport ?? (function_exists('acl_flows_api_call') ? 'acl_flows_api_call' : null);
        if ($transport === null) {
            return $this->error('Le connecteur Maivou est indisponible.', 503);
        }
        $response = $transport(compact('endpoint', 'method', 'body', 'params') + ['auth' => 'user']);
        if (! is_array($response) || ! isset($response['ok'])) {
            return $this->error('Réponse Maivou invalide.', 502);
        }
        return $response;
    }

    public function error(string $message, int $status = 422): array
    {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $message];
    }

    public function entity(array $response): array
    {
        $data = $response['data'] ?? [];
        return is_array($data) ? (is_array($data['data'] ?? null) ? $data['data'] : $data) : [];
    }

    public function buyerIdentity(): array
    {
        $response = $this->request('/me');
        if (! $response['ok']) return $response;
        $profile = $this->entity($response);
        $roles = $profile['user']['roles'] ?? $profile['roles'] ?? [];
        $isBuyer = false;
        foreach (is_array($roles) ? $roles : [] as $role) {
            if ((is_array($role) ? ($role['name'] ?? '') : $role) === 'acheteur') {
                $isBuyer = true;
                break;
            }
        }
        if (! $isBuyer) {
            return $this->error('Cette rubrique est réservée aux comptes Maivou avec le profil Acheteur.', 403) + ['reason' => 'buyer_role_required'];
        }
        $uuid = $profile['user']['uuid'] ?? $profile['uuid'] ?? null;
        if (! is_string($uuid) || ! $this->validUuid($uuid)) {
            return $this->error('Impossible de vérifier votre identité Maivou.', 403);
        }
        return ['ok' => true, 'data' => $uuid];
    }

    public function ownOrder(string $uuid): array
    {
        if (! $this->validUuid($uuid)) return $this->error('Référence de commande invalide.', 400);
        $identity = $this->buyerIdentity();
        if (! $identity['ok']) return $identity;
        $response = $this->request('/orders/' . $uuid);
        if (! $response['ok']) return $response;
        $order = $this->entity($response);
        if (($order['buyer_uuid'] ?? null) !== $identity['data']) {
            return $this->error('Cette commande n’est pas accessible à votre compte.', 403);
        }
        if (in_array($order['status'] ?? '', ['completed', 'delivered'], true)) {
            $order['allocated_lots'] = $this->lotsWithQrCodes($order['allocated_lots'] ?? []);
            if (is_array($response['data']['data'] ?? null)) {
                $response['data']['data'] = $order;
            } else {
                $response['data'] = $order;
            }
        }
        return $response;
    }

    /** Only called after ownership verification; attachment failures must not hide the order. */
    private function lotsWithQrCodes($lots): array
    {
        $result = [];
        $images = [];
        foreach (is_array($lots) ? $lots : [] as $lot) {
            if (! is_array($lot)) continue;
            $uuid = $lot['uuid'] ?? '';
            // Never accept a remote image URL or markup from the order snapshot.
            $lot['qr_image'] = null;
            if (is_string($uuid) && $this->validUuid($uuid)) {
                if (! array_key_exists($uuid, $images)) {
                    $images[$uuid] = $this->lotQrCode($uuid);
                }
                $lot['qr_image'] = $images[$uuid];
            }
            $result[] = $lot;
        }
        return $result;
    }

    private function lotQrCode(string $uuid): ?string
    {
        if (! class_exists(\DOMDocument::class)) return null;
        $response = $this->request('/lots/' . $uuid);
        if (! $response['ok']) return null;
        $attachments = $this->entity($response)['attachments'] ?? [];
        foreach (is_array($attachments) ? $attachments : [] as $attachment) {
            if (! is_array($attachment) || ($attachment['action'] ?? '') !== 'lot.document.qr_code'
                || ! is_string($attachment['uuid'] ?? null) || ! $this->validUuid($attachment['uuid'])
                || ($attachment['mime_type'] ?? '') !== 'image/svg+xml') continue;
            $file = $this->request('/lots/' . $uuid . '/attachments/' . $attachment['uuid']);
            $content = $file['data'] ?? null;
            if (! $file['ok'] || ! is_string($content) || trim($content) === '' || strlen($content) > 1048576
                || stripos($content, '<!DOCTYPE') !== false || stripos($content, '<!ENTITY') !== false) continue;
            $previous = libxml_use_internal_errors(true);
            try {
                $document = new \DOMDocument();
                $valid = $document->loadXML($content, LIBXML_NONET);
                if (! $valid || $document->documentElement->localName !== 'svg'
                    || $document->documentElement->namespaceURI !== 'http://www.w3.org/2000/svg') continue;
                // SVG stays in an isolated image context, never inserted as inline HTML.
                return 'data:image/svg+xml;base64,' . base64_encode($content);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }
        return null;
    }

    /** Filter all API pages before local pagination; never return a partial list on failure. */
    public function ownOrders(int $page, string $query): array
    {
        $identity = $this->buyerIdentity();
        if (! $identity['ok']) return $identity;
        $orders = [];
        $remotePage = 1;
        do {
            $params = ['page' => $remotePage, 'per_page' => 100];
            if ($query !== '') $params['q'] = substr($query, 0, 100);
            $response = $this->request('/orders', 'GET', [], $params);
            if (! $response['ok']) return $response;
            $payload = $response['data'] ?? [];
            $meta = $payload['meta'] ?? $payload;
            $last = (int) ($meta['last_page'] ?? 1);
            if (! is_array($payload['data'] ?? null) || (int) ($meta['current_page'] ?? $remotePage) !== $remotePage || $last < $remotePage || $last > 1000) {
                return $this->error('Pagination des commandes invalide.', 502);
            }
            foreach ($payload['data'] as $order) {
                if (is_array($order) && ($order['buyer_uuid'] ?? null) === $identity['data'] && is_string($order['uuid'] ?? null)) {
                    $orders[$order['uuid']] = $order;
                }
            }
        } while (++$remotePage <= $last);
        $lastPage = max(1, (int) ceil(count($orders) / 10));
        $page = min(max(1, $page), $lastPage);
        return ['ok' => true, 'data' => ['data' => array_slice(array_values($orders), ($page - 1) * 10, 10), 'meta' => ['current_page' => $page, 'last_page' => $lastPage]]];
    }

    public function products(): array
    {
        $items = [];
        $page = 1;
        do {
            $response = $this->request('/products', 'GET', [], ['is_active' => 1, 'per_page' => 100, 'page' => $page]);
            if (! $response['ok']) {
                return $response;
            }
            $payload = $response['data'] ?? null;
            if (! is_array($payload)) {
                return $this->error('Liste des produits invalide.', 502);
            }
            $rows = $payload['data'] ?? $payload;
            $last = (int) ($payload['last_page'] ?? 1);
            if (! is_array($rows) || (int) ($payload['current_page'] ?? $page) !== $page || $last < $page || $last > 1000) {
                return $this->error('Pagination des produits invalide.', 502);
            }
            foreach ($rows as $row) {
                if (is_array($row) && ! empty($row['uuid']) && in_array($row['is_active'] ?? false, [true, 1, '1'], true)) {
                    $items[] = $row;
                }
            }
        } while (++$page <= $last);
        return ['ok' => true, 'status' => 200, 'data' => $items];
    }

    public function validUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD', $uuid);
    }

    /** Never trust a posted buyer, unit or status; resolve them through Maivou. */
    public function save(array $values, string $uuid, string $intent): array
    {
        $identity = $this->buyerIdentity();
        if (! $identity['ok']) return $identity;
        if (! in_array($intent, ['draft', 'submit'], true) || ($uuid !== '' && ! $this->validUuid($uuid))) {
            return $this->error('Action ou référence de commande invalide.', 400);
        }
        $existing = [];
        if ($uuid !== '') {
            $response = $this->ownOrder($uuid);
            if (! $response['ok']) {
                return $response;
            }
            $existing = $this->entity($response);
            if (($existing['status'] ?? '') !== 'draft' || ! in_array('update', $existing['allowed_actions'] ?? [], true)
                || ($intent === 'submit' && ! in_array('submit', $existing['allowed_actions'] ?? [], true))) {
                return $this->error('Cette commande ne peut pas être modifiée ou soumise.', 403);
            }
        } else {
            $context = $this->request('/orders/context');
            if (! $context['ok']) {
                return $context;
            }
            if (($this->entity($context)['create'] ?? false) !== true) {
                return $this->error('Vous ne pouvez pas créer de commande pour votre compte.', 403);
            }
        }
        $productUuid = is_string($values['product_uuid'] ?? null) ? trim($values['product_uuid']) : '';
        $quantity = is_string($values['quantity'] ?? null) ? str_replace(',', '.', trim($values['quantity'])) : '';
        $date = is_string($values['expected_delivery_date'] ?? null) ? trim($values['expected_delivery_date']) : '';
        if (! preg_match('/^\d+(?:\.\d{1,3})?$/D', $quantity) || strlen($quantity) > 32 || ! preg_match('/[1-9]/', $quantity)) {
            return $this->error('Saisissez une quantité positive avec au maximum trois décimales.');
        }
        if ($date !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (! $parsed || $parsed->format('Y-m-d') !== $date) {
                return $this->error('La date de livraison est invalide.');
            }
        }
        $products = $this->products();
        if (! $products['ok']) {
            return $products;
        }
        $product = null;
        foreach ($products['data'] as $candidate) {
            if (($candidate['uuid'] ?? '') === $productUuid) {
                $product = $candidate;
                break;
            }
        }
        if ($product === null || ! $this->validUuid($productUuid)) {
            return $this->error('Sélectionnez un produit actif disponible.');
        }
        $unit = $product['assigned_packaging_unit'] ?? null;
        if (! is_string($unit) || trim($unit) === '' || strlen($unit) > 32) {
            return $this->error('Ce produit ne possède pas d’unité renseignée dans Maivou.');
        }
        $payload = ['product_uuid' => $productUuid, 'quantity' => $quantity, 'unit' => $unit, 'expected_delivery_date' => $date === '' ? null : $date];
        $meta = [];
        $selections = is_array($values['meta'] ?? null) ? $values['meta'] : [];
        // Only catalog-defined values may become product variants in the order.
        foreach ((array) ($product['meta'] ?? []) as $key => $definition) {
            if (is_array($definition) && (array_values($definition) === $definition)) {
                $choices = array_map('strval', array_filter($definition, 'is_scalar'));
                $selected = $selections[$key] ?? null;
                if (! is_string($selected) || ! in_array($selected, $choices, true)) {
                    return $this->error('Sélectionnez une valeur valide pour la variante « ' . $key . ' ».');
                }
                $meta[$key] = $selected;
            } elseif (is_string($definition)) {
                $meta[$key] = $definition;
            }
        }
        if ($meta !== [] || isset($existing['meta'])) {
            $payload['meta'] = $meta;
        }
        $saved = $this->request('/orders' . ($uuid === '' ? '' : '/' . $uuid), $uuid === '' ? 'POST' : 'PUT', $payload);
        if (! $saved['ok'] || $intent === 'draft') {
            return $saved;
        }
        $order = $this->entity($saved);
        $savedUuid = (string) ($order['uuid'] ?? '');
        if (! $this->validUuid($savedUuid)) {
            return $this->error('La réponse d’enregistrement ne contient pas de référence valide.', 502);
        }
        // Preserve the saved draft reference if the separate submission fails.
        if (! in_array('submit', $order['allowed_actions'] ?? [], true)) {
            return $this->error('Brouillon enregistré, mais soumission non autorisée.', 403) + ['saved_uuid' => $savedUuid];
        }
        $submitted = $this->request('/orders/' . $savedUuid . '/submit', 'POST');
        if (! $submitted['ok']) {
            $submitted['saved_uuid'] = $savedUuid;
        }
        return $submitted;
    }
}
