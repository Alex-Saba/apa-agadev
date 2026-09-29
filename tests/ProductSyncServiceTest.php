<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service {
    // In-memory WordPress storage exercises synchronization without a running site.
    function get_posts(array $args): array {
        $ids = [];
        foreach ($GLOBALS['product_test_posts'] as $id => $post) {
            if (($GLOBALS['product_test_meta'][$id][$args['meta_key']] ?? null) === $args['meta_value']) {
                $ids[] = $id;
            }
        }
        return array_slice($ids, 0, $args['posts_per_page']);
    }
    function wp_insert_post(array $post, bool $error = false) {
        if ($GLOBALS['product_test_save_failure'] ?? false) { return 0; }
        $id = $post['ID'] ?? count($GLOBALS['product_test_posts']) + 1;
        $GLOBALS['product_test_posts'][$id] = $post;
        return $id;
    }
    function update_post_meta($id, $key, $value) { $GLOBALS['product_test_meta'][$id][$key] = stripslashes($value); }
    function get_post_meta($id, $key, $single = true) { return $GLOBALS['product_test_meta'][$id][$key] ?? ''; }
    function wp_slash($value) { return is_array($value) ? array_map(__NAMESPACE__ . '\\wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
    if (! function_exists(__NAMESPACE__ . '\\update_option')) {
        function update_option($key, $value, $autoload = false) { $GLOBALS['product_test_options'][$key] = $value; }
    }
    function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
    function is_wp_error($value): bool { return false; }
    function get_post_thumbnail_id($id) { return $GLOBALS['product_test_meta'][$id]['_thumbnail_id'] ?? 0; }
    function wp_attachment_is_image($id): bool { return $id === 42; }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use PluginApaAgadev\Service\MaivouDataService;
    use PluginApaAgadev\Service\ProductSyncService;

    final class ProductSyncServiceTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['product_test_posts'] = [];
            $GLOBALS['product_test_meta'] = [];
            $GLOBALS['product_test_save_failure'] = false;
            $this->response([['id' => 7, 'sku' => 'P-7', 'name' => 'Miel', 'description' => "L’origine"]]);
        }

        private function response($data, bool $ok = true): void
        {
            $GLOBALS['apa_test_api_response'] = ['ok' => $ok, 'status' => $ok ? 200 : 403, 'data' => $data, 'error' => $ok ? null : 'Accès refusé'];
        }

        private function sync(): array { return (new ProductSyncService(new MaivouDataService()))->syncProducts(); }

        public function testBridgeSynchronizationAndPhotoSurviveUpdate(): void
        {
            self::assertSame(1, $this->sync()['synced']);
            self::assertSame(['endpoint' => '/machine/products', 'method' => 'GET', 'scope' => 'products.read', 'user_id' => 0], $GLOBALS['apa_test_api_arguments']);
            $GLOBALS['product_test_meta'][1]['_thumbnail_id'] = 42;
            $this->response([['id' => 7, 'sku' => 'P-7', 'name' => 'Miel actualisé', 'description' => "D'été"]]);
            self::assertSame(1, $this->sync()['synced']);
            self::assertCount(1, $GLOBALS['product_test_posts']);
            $resolved = ProductSyncService::resolveForLot(['code' => 'P-7', 'name' => 'Ancien nom'], 12);
            self::assertSame('Miel actualisé', $resolved['product']['name']);
            self::assertSame("D'été", $resolved['product']['description']);
            self::assertSame(42, $resolved['image_id']);
        }

        public function testFallbackAndMissingPhoto(): void
        {
            $fallback = ['code' => 'P-7', 'name' => 'Ancien'];
            self::assertSame(['product' => $fallback, 'image_id' => 12], ProductSyncService::resolveForLot($fallback, 12));
            $this->sync();
            self::assertSame(12, ProductSyncService::resolveForLot($fallback, 12)['image_id']);
            $GLOBALS['product_test_meta'][1]['_thumbnail_id'] = 99;
            self::assertSame(12, ProductSyncService::resolveForLot($fallback, 12)['image_id']);
        }

        public function testFailuresAndEmptyCatalogPreserveExistingProducts(): void
        {
            $this->sync();
            $this->response(null, false);
            self::assertSame(1, $this->sync()['errors']);
            $this->response(['unexpected' => []]);
            self::assertSame(1, $this->sync()['errors']);
            $this->response([]);
            self::assertSame(0, $this->sync()['errors']);
            self::assertCount(1, $GLOBALS['product_test_posts']);
        }

        public function testInvalidRecordsAndFailedWritesAreReported(): void
        {
            $this->response([['id' => 1, 'sku' => '', 'name' => 'Invalid'], ['id' => 2, 'sku' => 'P', 'name' => []]]);
            self::assertSame(2, $this->sync()['errors']);
            self::assertCount(0, $GLOBALS['product_test_posts']);
            $this->response([['id' => 3, 'sku' => 'P', 'name' => 'Valid']]);
            $GLOBALS['product_test_save_failure'] = true;
            self::assertSame(1, $this->sync()['errors']);
        }

        public function testCodeChangeKeepsPhotoAndAmbiguousCodesFallBack(): void
        {
            $this->sync();
            $GLOBALS['product_test_meta'][1]['_thumbnail_id'] = 42;
            $this->response([['id' => 7, 'sku' => 'NEW', 'name' => 'Miel']]);
            $this->sync();
            self::assertSame(42, ProductSyncService::resolveForLot(['code' => 'NEW'], 0)['image_id']);
            $this->response([['id' => 8, 'sku' => 'NEW', 'name' => 'Autre']]);
            $this->sync();
            $fallback = ['code' => 'NEW', 'name' => 'Lot'];
            self::assertSame(['product' => $fallback, 'image_id' => 0], ProductSyncService::resolveForLot($fallback, 0));
        }
    }
}
