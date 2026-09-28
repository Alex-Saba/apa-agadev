<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PluginApaAgadev\Service\OrderDataService;
use PluginApaAgadev\Service\OrderShortcodeService;

if (! function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle): void {}
}
if (! function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle): void {}
}
if (! function_exists('get_permalink')) {
    function get_permalink(): string { return 'https://wordpress.test/commandes/'; }
}
if (! function_exists('get_option')) {
    function get_option(string $name) { return '/%postname%/'; }
}
if (! function_exists('wp_nonce_field')) {
    function wp_nonce_field(string $action, string $name): void
    {
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($action) . '">';
    }
}

final class OrderSubmissionTest extends TestCase
{
    private const PRODUCT = '11111111-1111-4111-8111-111111111111';
    private const BUYER = '33333333-3333-4333-8333-333333333333';
    private const ORDER = '22222222-2222-4222-8222-222222222222';
    private array $calls = [];

    /** @dataProvider completedLotCases */
    public function testCompletedOrderLotsUseAuthenticatedExistingQrCodes(string $status, string $failure): void
    {
        $lotUuid = '44444444-4444-4444-8444-444444444444';
        $attachmentUuid = '55555555-5555-4555-8555-555555555555';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M0 0h20v20H0z"/></svg>';
        $calls = [];
        $service = new OrderDataService(function ($args) use ($status, $failure, $lotUuid, $attachmentUuid, $svg, &$calls) {
            self::assertSame('GET', $args['method']);
            self::assertSame('user', $args['auth']);
            $calls[] = $args['endpoint'];
            if ($args['endpoint'] === '/me') return ['ok' => true, 'data' => ['uuid' => self::BUYER, 'roles' => ['acheteur']]];
            if ($args['endpoint'] === '/orders/' . self::ORDER) return ['ok' => true, 'data' => ['data' => [
                'uuid' => self::ORDER, 'buyer_uuid' => $failure === 'foreign' ? $lotUuid : self::BUYER,
                'status' => $status, 'unit' => 'kg', 'allocated_lots' => $failure === 'empty' ? [] : [[
                    'uuid' => $lotUuid, 'quantity' => '10.000', 'snapshot' => ['code' => '<LOT-1>'],
                    'qr_image' => 'https://untrusted.test/image.svg',
                ]],
            ]]];
            if ($args['endpoint'] === '/lots/' . $lotUuid) {
                if ($failure === 'denied') return ['ok' => false, 'status' => 403];
                return ['ok' => true, 'data' => ['attachments' => $failure === 'missing' ? [] : [[
                    'uuid' => $attachmentUuid, 'action' => 'lot.document.qr_code', 'mime_type' => 'image/svg+xml',
                ]]]];
            }
            self::assertSame('/lots/' . $lotUuid . '/attachments/' . $attachmentUuid, $args['endpoint']);
            return ['ok' => $failure !== 'download', 'data' => $failure === 'invalid' ? '<html>Login</html>' : $svg];
        });
        if ($failure === 'foreign') {
            self::assertSame(403, $service->ownOrder(self::ORDER)['status']);
            self::assertCount(2, $calls);
            return;
        }
        $GLOBALS['apa_test_logged_in'] = true;
        $_GET = ['order_uuid' => self::ORDER];
        try {
            $html = (new OrderShortcodeService($service))->render();
            self::assertStringNotContainsString('https://untrusted.test', $html);
            if (! in_array($status, ['completed', 'delivered'], true)) {
                self::assertCount(2, $calls);
                self::assertStringNotContainsString('data:image/svg+xml', $html);
                return;
            }
            self::assertStringContainsString('Lots composant la commande', $html);
            if ($failure === 'empty') {
                self::assertStringContainsString('Les informations des lots ne sont pas disponibles', $html);
                self::assertCount(2, $calls);
            } else {
                self::assertStringContainsString('&lt;LOT-1&gt;', $html);
                if ($failure === '') {
                    self::assertStringContainsString('data:image/svg+xml;base64,' . base64_encode($svg), $html);
                    self::assertStringContainsString('QR code du lot &lt;LOT-1&gt;', $html);
                } else {
                    self::assertStringContainsString('QR code indisponible', $html);
                    self::assertStringNotContainsString('data:image/svg+xml', $html);
                }
            }
        } finally {
            $_GET = [];
            unset($GLOBALS['apa_test_logged_in']);
        }
    }

    public static function completedLotCases(): array
    {
        return [
            ['completed', ''], ['delivered', ''], ['submitted', ''],
            ['completed', 'denied'], ['completed', 'missing'], ['completed', 'download'],
            ['completed', 'invalid'], ['completed', 'empty'], ['completed', 'foreign'],
        ];
    }

    private function service(?string $unit = 'kg', string $status = 'draft', bool $submitFails = false): OrderDataService
    {
        return new OrderDataService(function (array $args) use ($unit, $status, $submitFails): array {
            $this->calls[] = $args;
            $data = [];
            if ($args['endpoint'] === '/me') return ['ok' => true, 'data' => ['uuid' => self::BUYER, 'roles' => [['name' => 'acheteur']]]];
            if ($args['endpoint'] === '/orders/context') {
                $data = ['data' => ['create' => true]];
            } elseif ($args['endpoint'] === '/products') {
                $data = ['data' => [['uuid' => self::PRODUCT, 'name' => 'Résine', 'is_active' => true, 'assigned_packaging_unit' => $unit]], 'current_page' => 1, 'last_page' => 1];
            } elseif (str_ends_with($args['endpoint'], '/submit') && $submitFails) {
                return ['ok' => false, 'status' => 422, 'error' => 'Soumission refusée'];
            } else {
                $data = ['data' => ['uuid' => self::ORDER, 'buyer_uuid' => self::BUYER, 'product_uuid' => self::PRODUCT, 'status' => $status, 'allowed_actions' => ['update', 'submit']]];
            }
            return ['ok' => true, 'status' => 200, 'data' => $data];
        });
    }

    private function values(): array
    {
        return ['product_uuid' => self::PRODUCT, 'quantity' => '10.001', 'expected_delivery_date' => '', 'unit' => 'forged', 'buyer_uuid' => 'forged', 'status' => 'delivered'];
    }

    public function testBuyerRoleIsRequiredIndependentlyOfPermissions(): void
    {
        foreach ([['acheteur', [], true], ['transformateur', ['order-read'], false]] as [$role, $permissions, $allowed]) {
            $calls = [];
            $service = new OrderDataService(function ($args) use ($role, $permissions, &$calls) {
                $calls[] = $args['endpoint'];
                return ['ok' => true, 'data' => ['uuid' => self::BUYER, 'roles' => [['name' => $role]], 'permissions' => $permissions]];
            });
            self::assertSame($allowed, $service->buyerIdentity()['ok']);
            if (! $allowed) {
                self::assertSame(403, $service->ownOrders(1, '')['status']);
                self::assertSame(403, $service->ownOrder(self::ORDER)['status']);
                self::assertSame(403, $service->save($this->values(), '', 'draft')['status']);
                self::assertSame(['/me'], array_values(array_unique($calls)));
            }
        }
    }

    public function testBuyerFilteringPrecedesPaginationAndRejectsOtherBuyers(): void
    {
        $writes = [];
        $service = new OrderDataService(function ($args) use (&$writes) {
            if ($args['method'] !== 'GET') $writes[] = $args;
            if ($args['endpoint'] === '/me') return ['ok' => true, 'data' => ['uuid' => self::BUYER, 'roles' => [['name' => 'acheteur']]]];
            if ($args['endpoint'] !== '/orders') {
                return ['ok' => true, 'data' => ['uuid' => self::ORDER, 'buyer_uuid' => self::PRODUCT, 'status' => 'draft', 'allowed_actions' => ['update']]];
            }
            $page = $args['params']['page'];
            $rows = [['uuid' => 'foreign-' . $page, 'buyer_uuid' => self::PRODUCT, 'author_uuid' => self::BUYER]];
            for ($i = 0; $i < 6; $i++) {
                $rows[] = ['uuid' => 'own-' . $page . '-' . $i, 'buyer_uuid' => self::BUYER, 'author_uuid' => self::PRODUCT];
            }
            return ['ok' => true, 'data' => ['data' => $rows, 'meta' => ['current_page' => $page, 'last_page' => 2]]];
        });
        $list = $service->ownOrders(2, '');
        self::assertTrue($list['ok']);
        self::assertCount(2, $list['data']['data']);
        self::assertSame(2, $list['data']['meta']['last_page']);
        foreach ($list['data']['data'] as $order) self::assertSame(self::BUYER, $order['buyer_uuid']);
        self::assertSame(403, $service->ownOrder(self::ORDER)['status']);
        self::assertSame(403, $service->save($this->values(), self::ORDER, 'draft')['status']);
        self::assertSame([], $writes);
    }

    public function testSavedDraftAndSubmittedOrderShowConfirmationInsteadOfForm(): void
    {
        $GLOBALS['apa_test_logged_in'] = true;
        $_GET = ['order_uuid' => self::ORDER, 'order_saved' => '1'];
        try {
            foreach (['draft', 'submitted'] as $status) {
                $service = new OrderDataService(function ($args) use ($status) {
                    self::assertSame('GET', $args['method']);
                    if ($args['endpoint'] === '/me') return ['ok' => true, 'data' => ['uuid' => self::BUYER, 'roles' => [['name' => 'acheteur']]]];
                    if ($args['endpoint'] === '/orders') {
                        return ['ok' => true, 'data' => ['data' => [], 'meta' => ['last_page' => 1]]];
                    }
                    return ['ok' => true, 'data' => ['data' => ['uuid' => self::ORDER, 'buyer_uuid' => self::BUYER, 'status' => $status, 'allowed_actions' => ['update'], 'create' => true]]];
                });
                $html = (new OrderShortcodeService($service))->render();
                self::assertStringContainsString('data-apa-order-success', $html);
                self::assertStringContainsString($status === 'draft' ? 'Votre brouillon a bien été enregistré' : 'Votre commande a bien été soumise', $html);
                self::assertStringNotContainsString('name="order[quantity]"', $html);
            }
        } finally {
            $_GET = [];
            unset($GLOBALS['apa_test_logged_in']);
        }
    }

    public function testVariantsAreValidatedForCreationAndModification(): void
    {
        $base = $this->service();
        $service = new OrderDataService(function ($args) use ($base) {
            $response = $base->request($args['endpoint'], $args['method'], $args['body'], $args['params']);
            if ($args['endpoint'] === '/products') {
                $response['data']['data'][0]['meta'] = ['Qualité' => ['Brute', 'Purifiée']];
            }
            return $response;
        });
        foreach (['', self::ORDER] as $uuid) {
            $values = $this->values();
            $values['meta'] = ['Qualité' => 'Purifiée', 'injected' => 'ignored'];
            self::assertTrue($service->save($values, $uuid, 'draft')['ok']);
            self::assertSame(['Qualité' => 'Purifiée'], end($this->calls)['body']['meta']);
            $this->calls = [];
            $values['meta']['Qualité'] = 'Invalid';
            self::assertFalse($service->save($values, $uuid, 'draft')['ok']);
            self::assertSame([], array_filter($this->calls, fn ($c) => $c['method'] !== 'GET'));
        }
    }

    public function testDraftDisplaysCatalogUnitAndMissingUnitExplicitly(): void
    {
        $GLOBALS['apa_test_logged_in'] = true;
        $_GET = ['order_uuid' => self::ORDER];
        try {
            $html = (new OrderShortcodeService($this->service('kg')))->renderForm();
            self::assertStringContainsString('data-apa-order-unit="kg"', $html);
            self::assertStringContainsString('aria-live="polite">(kg)</span>', $html);
            self::assertStringNotContainsString('name="order[unit]"', $html);
            $html = (new OrderShortcodeService($this->service(null)))->renderForm();
            self::assertStringContainsString('aria-live="polite">(unité non renseignée)</span>', $html);
            self::assertSame([], array_filter($this->calls, fn ($c) => $c['method'] !== 'GET'));
        } finally {
            $_GET = [];
            unset($GLOBALS['apa_test_logged_in']);
        }
    }

    public function testCreateUsesProductUnitAndNoPostedBuyerOrStatus(): void
    {
        $response = $this->service()->save($this->values(), '', 'draft');
        self::assertTrue($response['ok']);
        $call = end($this->calls);
        self::assertSame('POST', $call['method']);
        self::assertSame('/orders', $call['endpoint']);
        self::assertSame('user', $call['auth']);
        self::assertSame(['product_uuid' => self::PRODUCT, 'quantity' => '10.001', 'unit' => 'kg', 'expected_delivery_date' => null], $call['body']);
    }

    public function testUpdateUsesPutAndKeepsExactDecimalAndDate(): void
    {
        $values = $this->values();
        $values['quantity'] = '12345678901234567890,123';
        $values['expected_delivery_date'] = '2026-12-20';
        self::assertTrue($this->service()->save($values, self::ORDER, 'draft')['ok']);
        $call = end($this->calls);
        self::assertSame('PUT', $call['method']);
        self::assertSame('/orders/' . self::ORDER, $call['endpoint']);
        self::assertSame('12345678901234567890.123', $call['body']['quantity']);
        self::assertSame('2026-12-20', $call['body']['expected_delivery_date']);
    }

    public function testMissingUnitBlocksWrites(): void
    {
        self::assertFalse($this->service(null)->save($this->values(), '', 'draft')['ok']);
        self::assertSame([], array_filter($this->calls, fn ($c) => $c['method'] !== 'GET'));
    }

    public function testNonDraftCannotBeChanged(): void
    {
        self::assertSame(403, $this->service('kg', 'submitted')->save($this->values(), self::ORDER, 'draft')['status']);
        self::assertCount(3, $this->calls);
    }

    public function testFailedSubmissionRetainsSavedDraftReference(): void
    {
        $response = $this->service('kg', 'draft', true)->save($this->values(), '', 'submit');
        self::assertFalse($response['ok']);
        self::assertSame(self::ORDER, $response['saved_uuid']);
        self::assertSame('/orders/' . self::ORDER . '/submit', end($this->calls)['endpoint']);
    }

    public function testInvalidQuantityAndDateCannotWrite(): void
    {
        foreach (['0', '-1', '1.2345', '1e3'] as $quantity) {
            $this->calls = [];
            $values = $this->values();
            $values['quantity'] = $quantity;
            self::assertFalse($this->service()->save($values, '', 'draft')['ok']);
            self::assertSame([], array_filter($this->calls, fn ($c) => $c['method'] !== 'GET'));
        }
        $values = $this->values();
        $values['expected_delivery_date'] = '2026-02-30';
        self::assertFalse($this->service()->save($values, '', 'draft')['ok']);
    }

    public function testUnknownProductCannotWrite(): void
    {
        $values = $this->values();
        $values['product_uuid'] = self::ORDER;
        self::assertFalse($this->service()->save($values, '', 'draft')['ok']);
        self::assertSame([], array_filter($this->calls, fn ($c) => $c['method'] !== 'GET'));
    }

    public function testNonceFailureMakesNoApiCalls(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['apa_order_intent' => 'draft', '_apa_order_nonce' => 'bad'];
        $GLOBALS['apa_test_logged_in'] = true;
        try {
            (new OrderShortcodeService($this->service()))->handlePost();
            self::assertSame([], $this->calls);
        } finally {
            $_POST = [];
            unset($_SERVER['REQUEST_METHOD'], $GLOBALS['apa_test_logged_in']);
        }
    }

    public function testProductPaginationIsFollowed(): void
    {
        $pages = [];
        $service = new OrderDataService(function ($args) use (&$pages) {
            $page = $args['params']['page'];
            $pages[] = $page;
            return ['ok' => true, 'data' => ['current_page' => $page, 'last_page' => 2, 'data' => [['uuid' => (string) $page, 'is_active' => true]]]];
        });
        self::assertCount(2, $service->products()['data']);
        self::assertSame([1, 2], $pages);
    }

    public function testOpeningFormHasOnlyApprovedFieldsAndNeverWrites(): void
    {
        $GLOBALS['apa_test_logged_in'] = true;
        $_GET = [];
        try {
            $html = (new OrderShortcodeService($this->service()))->renderForm();
            self::assertStringContainsString('name="order[product_uuid]"', $html);
            self::assertStringContainsString('name="order[quantity]"', $html);
            self::assertStringContainsString('Produit et variantes', $html);
            self::assertStringContainsString('Quantité et livraison', $html);
            self::assertStringContainsString('data-apa-order-feedback', $html);
            self::assertStringContainsString('name="order[expected_delivery_date]"', $html);
            self::assertStringNotContainsString('name="order[unit]"', $html);
            self::assertStringNotContainsString('buyer_uuid', $html);
            self::assertSame([], array_filter($this->calls, fn ($c) => $c['method'] !== 'GET'));
        } finally {
            unset($GLOBALS['apa_test_logged_in']);
        }
    }

    public function testListPaginationAndEscapedDetailsRenderWithoutWrites(): void
    {
        $GLOBALS['apa_test_logged_in'] = true;
        $service = new OrderDataService(function ($args) {
            self::assertSame('GET', $args['method']);
            $order = ['uuid' => self::ORDER, 'buyer_uuid' => self::BUYER, 'buyer' => ['firstname' => '<Alex>', 'lastname' => 'Acheteur'], 'code' => '<script>bad</script>', 'created_at' => '2026-09-26T16:17:03Z', 'meta' => ['formes' => 'Poudre'], 'status' => 'submitted', 'product' => ['name' => 'Résine'], 'quantity' => '10.000', 'unit' => 'kg', 'allowed_actions' => ['update']];
            if ($args['endpoint'] === '/me') return ['ok' => true, 'data' => ['uuid' => self::BUYER, 'roles' => [['name' => 'acheteur']]]];
            if ($args['endpoint'] === '/orders/context') {
                return ['ok' => true, 'data' => ['data' => ['create' => true]]];
            }
            return ['ok' => true, 'data' => $args['endpoint'] === '/orders' ? ['data' => [$order], 'meta' => ['last_page' => 2]] : ['data' => $order]];
        });
        try {
            $_GET = [];
            $shortcode = new OrderShortcodeService($service);
            $html = $shortcode->render();
            self::assertStringNotContainsString('Suivant', $html);
            self::assertStringContainsString('&lt;script&gt;', $html);
            $_GET = ['order_uuid' => self::ORDER];
            $html = $shortcode->render();
            self::assertStringNotContainsString('data-apa-order-modal', $html);
            self::assertStringNotContainsString('role="dialog"', $html);
            self::assertStringNotContainsString('Modifier / soumettre', $html);
            self::assertStringNotContainsString('<script>', $html);
            self::assertStringNotContainsString('Télécharger', $html);
            self::assertStringContainsString('acl_shortcode_agreement_detail_header', $html);
            self::assertStringContainsString('26/09/2026', $html);
            self::assertStringContainsString('Formes', $html);
            self::assertStringContainsString('<caption>Articles commandés</caption>', $html);
            self::assertStringContainsString('&lt;Alex&gt; Acheteur', $html);
            self::assertStringContainsString('<th scope="col">Unité</th>', $html);
            self::assertStringContainsString('apa-order-document-number">10.000</td>', $html);
        } finally {
            $_GET = [];
            unset($GLOBALS['apa_test_logged_in']);
        }
    }

    public function testEmptyListMatchesAgreementLayoutAndCreationOpensModal(): void
    {
        $GLOBALS['apa_test_logged_in'] = true;
        $_GET = ['view' => 'commandes'];
        $service = new OrderDataService(function ($args) {
            self::assertSame('GET', $args['method']);
            if ($args['endpoint'] === '/me') return ['ok' => true, 'data' => ['uuid' => self::BUYER, 'roles' => [['name' => 'acheteur']]]];
            if ($args['endpoint'] === '/orders/context') {
                return ['ok' => true, 'data' => ['data' => ['create' => true]]];
            }
            return ['ok' => true, 'data' => ['data' => [], 'current_page' => 1, 'last_page' => 1]];
        });
        try {
            $shortcode = new OrderShortcodeService($service);
            $html = $shortcode->render();
            self::assertStringContainsString('acl_shortcode_agreements_empty', $html);
            self::assertStringContainsString('Ajouter une commande', $html);
            self::assertStringNotContainsString('Pagination des commandes', $html);
            self::assertStringNotContainsString('name="order_search"', $html);
            self::assertStringContainsString('view=commandes', $html);
            $_GET['order_action'] = 'new';
            $html = $shortcode->render();
            self::assertStringContainsString('data-apa-order-modal', $html);
            self::assertStringContainsString('name="order[product_uuid]"', $html);
            self::assertStringContainsString('aria-modal="true"', $html);
        } finally {
            $_GET = [];
            unset($GLOBALS['apa_test_logged_in']);
        }
    }
}
