<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service {
    // Isolate mail and persistence from real WordPress while keeping real PDF generation.
    final class ConfirmationRedirect extends \RuntimeException {}
    function wp_safe_redirect($url) { throw new ConfirmationRedirect($url); }
    function wp_get_current_user() { return (object) ($GLOBALS['confirmation_user'] ?? ['ID' => 7, 'user_email' => 'submitter@example.test']); }
    function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
    function add_option($key, $value, $unused = '', $autoload = false) {
        if (isset($GLOBALS['confirmation_options'][$key])) return false;
        $GLOBALS['confirmation_options'][$key] = $value;
        return true;
    }
    function update_option($key, $value, $autoload = false) { $GLOBALS['confirmation_options'][$key] = $value; return true; }
    function delete_option($key) { unset($GLOBALS['confirmation_options'][$key]); return true; }
    function wp_mail($to, $subject, $body, $headers, $attachments) {
        $GLOBALS['confirmation_mail'][] = compact('to', 'subject', 'body', 'headers', 'attachments') + ['pdf' => file_get_contents($attachments[0])];
        if (($GLOBALS['confirmation_mode'] ?? '') === 'throw') throw new \RuntimeException('Mail transport failed');
        return ($GLOBALS['confirmation_mode'] ?? '') !== 'fail';
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use PluginApaAgadev\Service\OrderConfirmationService;

    final class OrderConfirmationTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['confirmation_options'] = [];
            $GLOBALS['confirmation_mail'] = [];
            unset($GLOBALS['confirmation_user'], $GLOBALS['confirmation_mode']);
        }

        private function response(): array
        {
            return ['ok' => true, 'data' => ['data' => [
                'uuid' => '22222222-2222-4222-8222-222222222222', 'code' => 'CMD-2026-000002', 'status' => 'submitted',
                'buyer' => ['firstname' => 'Alex O', 'lastname' => 'SABA-OKOUYI', 'email' => 'buyer@example.test'],
                'product' => ['name' => 'Poudre de moringa'], 'quantity' => '100.000', 'unit' => 'kg', 'allocated_quantity' => '0.000',
                'meta' => ['formes' => 'Poudre', 'qualité' => 'Premium', 'couleurs' => 'Clair'],
                'created_at' => '2026-09-26T12:00:00Z', 'submitted_at' => '2026-09-28T12:00:00Z',
            ]]];
        }

        public function testSuccessfulPostTriggersEmailBeforeRedirectEvenIfMailFails(): void
        {
            $GLOBALS['apa_test_logged_in'] = true;
            $GLOBALS['confirmation_mode'] = 'fail';
            $oldServer = $_SERVER;
            $oldPost = $_POST;
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['apa_order_intent' => 'submit', '_apa_order_nonce' => 'apa_order_save', 'order' => [
                'product_uuid' => '11111111-1111-4111-8111-111111111111', 'quantity' => '100.000',
            ]];
            $response = $this->response();
            $calls = [];
            $data = new \PluginApaAgadev\Service\OrderDataService(function ($args) use ($response, &$calls) {
                $calls[] = $args['endpoint'];
                if ($args['endpoint'] === '/me') return ['ok' => true, 'data' => ['uuid' => '33333333-3333-4333-8333-333333333333', 'roles' => ['acheteur']]];
                if ($args['endpoint'] === '/orders/context') return ['ok' => true, 'data' => ['create' => true]];
                if ($args['endpoint'] === '/products') return ['ok' => true, 'data' => ['data' => [[
                    'uuid' => '11111111-1111-4111-8111-111111111111', 'is_active' => true, 'assigned_packaging_unit' => 'kg',
                ]]]];
                if ($args['endpoint'] === '/orders') return ['ok' => true, 'data' => ['uuid' => $response['data']['data']['uuid'], 'status' => 'draft', 'allowed_actions' => ['submit']]];
                self::assertStringEndsWith('/submit', $args['endpoint']);
                self::assertSame([], $GLOBALS['confirmation_mail']);
                return $response;
            });
            try {
                (new \PluginApaAgadev\Service\OrderShortcodeService($data))->handlePost();
                self::fail('Expected confirmation redirect');
            } catch (\PluginApaAgadev\Service\ConfirmationRedirect $redirect) {
                self::assertStringContainsString('order_saved=1', $redirect->getMessage());
                self::assertCount(1, $GLOBALS['confirmation_mail']);
                self::assertCount(5, $calls);
            } finally {
                $_SERVER = $oldServer;
                $_POST = $oldPost;
                unset($GLOBALS['apa_test_logged_in']);
            }
        }

        public function testSubmissionSendsRealPdfToSubmitterOnceAndDeletesTemporaryFile(): void
        {
            $service = new OrderConfirmationService();
            self::assertTrue($service->send($this->response(), 'submit'));
            self::assertFalse($service->send($this->response(), 'submit'));
            self::assertCount(1, $GLOBALS['confirmation_mail']);
            $mail = $GLOBALS['confirmation_mail'][0];
            self::assertSame('submitter@example.test', $mail['to']);
            self::assertStringContainsString('CMD-2026-000002', $mail['subject']);
            self::assertStringStartsWith('%PDF-', $mail['pdf']);
            self::assertStringEndsWith('.pdf', $mail['attachments'][0]);
            self::assertFileDoesNotExist($mail['attachments'][0]);
            self::assertSame('accepted', array_values($GLOBALS['confirmation_options'])[0]['status']);
        }

        public function testDraftFailureAndInvalidResponseNeverSend(): void
        {
            $service = new OrderConfirmationService();
            $response = $this->response();
            self::assertFalse($service->send($response, 'draft'));
            $response['ok'] = false;
            self::assertFalse($service->send($response, 'submit'));
            $response['ok'] = true;
            $response['data']['data']['status'] = 'draft';
            self::assertFalse($service->send($response, 'submit'));
            $response['data']['data']['status'] = 'submitted';
            $response['data']['data']['uuid'] = '';
            self::assertFalse($service->send($response, 'submit'));
            self::assertSame([], $GLOBALS['confirmation_mail']);
            self::assertSame([], $GLOBALS['confirmation_options']);
        }

        public function testMissingEmailDoesNotFallBackToBuyer(): void
        {
            $GLOBALS['confirmation_user'] = ['ID' => 7, 'user_email' => ''];
            self::assertFalse((new OrderConfirmationService())->send($this->response(), 'submit'));
            self::assertSame([], $GLOBALS['confirmation_mail']);
        }

        /** @dataProvider mailFailures */
        public function testMailFailureIsContainedAndAnExplicitRetryIsPossible(string $mode): void
        {
            $GLOBALS['confirmation_mode'] = $mode;
            $service = new OrderConfirmationService();
            self::assertFalse($service->send($this->response(), 'submit'));
            self::assertSame([], $GLOBALS['confirmation_options']);
            self::assertFileDoesNotExist($GLOBALS['confirmation_mail'][0]['attachments'][0]);
            unset($GLOBALS['confirmation_mode']);
            self::assertTrue($service->send($this->response(), 'submit'));
        }
        public static function mailFailures(): array { return [['fail'], ['throw']]; }

        public function testPdfTemplateEscapesValuesAndOmitsNavigationAndHeaderStatus(): void
        {
            $order = $this->response()['data']['data'];
            $order['product']['name'] = '<script>alert(1)</script>';
            $brand_logo_data_uri = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(PLUGIN_APA_AGADEV_PATH . 'assets/images/logo-agadev.svg'));
            ob_start();
            require PLUGIN_APA_AGADEV_PATH . 'templates/order-confirmation-pdf.php';
            $html = ob_get_clean();
            self::assertStringNotContainsString('<script>', $html);
            self::assertStringContainsString('&lt;script&gt;', $html);
            self::assertStringNotContainsString('Retour', $html);
            self::assertSame(1, substr_count($html, '>Soumise<'));
            self::assertStringContainsString('28/09/2026', $html);
            self::assertStringContainsString('Qualité', $html);
            self::assertStringContainsString($brand_logo_data_uri, $html);
            self::assertStringContainsString('République Gabonaise · Union · Travail · Justice', $html);
            self::assertStringContainsString('Plateforme Maivou', $html);
            self::assertStringNotContainsString('Document administratif', $html);
        }
    }
}
