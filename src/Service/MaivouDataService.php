<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

/**
 * Reads APA data through ACL WordPress API Bridge.
 */
final class MaivouDataService
{
    private const PUBLIC_LOT_STATUS = 'ready';

    private const LOTS_PER_PAGE = 100;

    /**
     * Returns the role-filtered APA agreement form schema.
     *
     * @return array{ok:bool,status:int,data:mixed,headers:array,set_cookie:array,error:?string}
     */
    public function getAgreementForm(?string $role = null): array
    {
        $body = ['key' => 'agreement'];

        if (is_string($role) && trim($role) !== '') {
            $body['role'] = sanitize_key($role);
        }

        return $this->call([
            'endpoint' => '/_catalog',
            'method' => 'POST',
            'body' => $body,
            'auth' => 'user',
        ]);
    }

    /**
     * Creates an APA agreement for the authenticated Maivou user.
     *
     * @param array<string, mixed> $agreement
     * @return array{ok:bool,status:int,data:mixed,headers:array,set_cookie:array,error:?string}
     */
    public function createAgreement(array $agreement): array
    {
        return $this->call([
            'endpoint' => '/agreements',
            'method' => 'POST',
            'body' => $agreement,
            'auth' => 'user',
        ]);
    }

    /**
     * Loads the option collections referenced by the filtered catalog.
     *
     * Only endpoints returned by Maivou's trusted catalog are requested. The
     * resulting map lets the template resolve selects without direct browser
     * access to the protected API.
     *
     * @param array<string, mixed> $catalog
     * @return array{ok:bool,status:int,data:mixed,headers:array,set_cookie:array,error:?string}
     */
    public function getAgreementOptions(array $catalog): array
    {
        $endpoints = [];
        $this->collectOptionEndpoints($catalog, $endpoints);
        $collections = [];

        foreach (array_keys($endpoints) as $catalog_endpoint) {
            $endpoint = preg_replace('#^/api#', '', $catalog_endpoint);

            if (! is_string($endpoint) || ! str_starts_with($endpoint, '/')) {
                return $this->errorResponse(
                    502,
                    __('Un endpoint d’options du formulaire APA est invalide.', 'plugin-apa-agadev')
                );
            }

            $collections[$catalog_endpoint] = [];
            $page = 1;

            do {
                $response = $this->call([
                    'endpoint' => $endpoint,
                    'method' => 'GET',
                    'params' => ['per_page' => 100, 'page' => $page],
                    'auth' => 'user',
                ]);

                if (! $response['ok']) {
                    return $response;
                }

                $payload = $response['data'];
                $is_paginated = is_array($payload) && is_array($payload['data'] ?? null);
                $items = $is_paginated ? $payload['data'] : $payload;

                if (! is_array($items)) {
                    return $this->errorResponse(
                        502,
                        __('La réponse Maivou pour les options APA est invalide.', 'plugin-apa-agadev')
                    );
                }

                $collections[$catalog_endpoint] = array_merge($collections[$catalog_endpoint], $items);
                $current_page = $is_paginated ? (int) ($payload['current_page'] ?? 1) : 1;
                $last_page = $is_paginated ? (int) ($payload['last_page'] ?? $current_page) : 1;

                if ($current_page < 1 || $last_page < $current_page) {
                    return $this->errorResponse(
                        502,
                        __('La pagination Maivou pour les options APA est invalide.', 'plugin-apa-agadev')
                    );
                }

                $page = $current_page + 1;
            } while ($current_page < $last_page);
        }

        return [
            'ok' => true,
            'status' => 200,
            'data' => $collections,
            'headers' => [],
            'set_cookie' => [],
            'error' => null,
        ];
    }

    /**
     * Returns the agreements visible to the current WordPress user.
     *
     * @return array{ok:bool,status:int,data:mixed,headers:array,set_cookie:array,error:?string}
     */
    public function getAgreements(): array
    {
        return $this->call([
            'endpoint' => '/agreements',
            'method' => 'GET',
            'auth' => 'user',
        ]);
    }

    /**
     * Reads every visible lot page and retains only public ready lots.
     *
     * The current Maivou endpoint does not expose a status filter. Walking the
     * complete pagination avoids returning an incomplete first-page subset.
     *
     * @return array{ok:bool,status:int,data:mixed,headers:array,set_cookie:array,error:?string}
     */
    public function getPublicLots(): array
    {
        $public_lots = [];
        $page = 1;

        do {
            $response = $this->call([
                'endpoint' => '/lots',
                'method' => 'GET',
                'params' => [
                    'per_page' => self::LOTS_PER_PAGE,
                    'page' => $page,
                ],
                'auth' => 'user',
            ]);

            if (! $response['ok']) {
                return $response;
            }

            $payload = $response['data'];

            if (
                ! is_array($payload)
                || ! isset($payload['data'], $payload['current_page'], $payload['last_page'])
                || ! is_array($payload['data'])
            ) {
                return $this->errorResponse(
                    502,
                    __('La réponse Maivou pour les lots est invalide.', 'plugin-apa-agadev')
                );
            }

            foreach ($payload['data'] as $lot) {
                if (
                    is_array($lot)
                    && isset($lot['status'])
                    && (string) $lot['status'] === self::PUBLIC_LOT_STATUS
                ) {
                    $public_lots[] = $lot;
                }
            }

            $current_page = (int) $payload['current_page'];
            $last_page = (int) $payload['last_page'];

            if ($current_page < 1 || $last_page < $current_page) {
                return $this->errorResponse(
                    502,
                    __('La pagination Maivou pour les lots est invalide.', 'plugin-apa-agadev')
                );
            }

            $page = $current_page + 1;
        } while ($current_page < $last_page);

        return [
            'ok' => true,
            'status' => 200,
            'data' => $public_lots,
            'headers' => [],
            'set_cookie' => [],
            'error' => null,
        ];
    }

    /**
     * Recursively discovers protected option endpoints in the catalog.
     *
     * @param array<string, mixed> $node
     * @param array<string, true> $endpoints
     */
    private function collectOptionEndpoints(array $node, array &$endpoints): void
    {
        if (isset($node['optionsEndpoint']) && is_string($node['optionsEndpoint'])) {
            $endpoints[$node['optionsEndpoint']] = true;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collectOptionEndpoints($value, $endpoints);
            }
        }
    }

    /**
     * Delegates all HTTP and authentication concerns to the required Bridge.
     *
     * @param array<string, mixed> $arguments
     * @return array{ok:bool,status:int,data:mixed,headers:array,set_cookie:array,error:?string}
     */
    private function call(array $arguments): array
    {
        if (! function_exists('acl_flows_api_call')) {
            return $this->errorResponse(
                503,
                __('ACL WordPress API Bridge est absent ou inactif.', 'plugin-apa-agadev')
            );
        }

        $response = \acl_flows_api_call($arguments);

        if (! is_array($response) || ! array_key_exists('ok', $response)) {
            return $this->errorResponse(
                502,
                __('La réponse de ACL WordPress API Bridge est invalide.', 'plugin-apa-agadev')
            );
        }

        return [
            'ok' => (bool) $response['ok'],
            'status' => (int) ($response['status'] ?? 500),
            'data' => $response['data'] ?? null,
            'headers' => is_array($response['headers'] ?? null) ? $response['headers'] : [],
            'set_cookie' => is_array($response['set_cookie'] ?? null) ? $response['set_cookie'] : [],
            'error' => isset($response['error']) ? (string) $response['error'] : null,
        ];
    }

    /**
     * Builds the same explicit response contract used by the Bridge.
     *
     * @return array{ok:false,status:int,data:null,headers:array,set_cookie:array,error:string}
     */
    private function errorResponse(int $status, string $message): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'headers' => [],
            'set_cookie' => [],
            'error' => $message,
        ];
    }
}
