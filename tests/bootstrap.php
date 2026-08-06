<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/wordpress/');
define('PLUGIN_APA_AGADEV_PATH', dirname(__DIR__) . '/');

require dirname(__DIR__) . '/vendor/autoload.php';

final class ApaAgadevWpDieException extends RuntimeException
{
    public function __construct(string $message, public readonly int $response)
    {
        parent::__construct($message);
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = ''): void
    {
        echo esc_html__($text, $domain);
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return esc_html($text);
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (! function_exists('sanitize_html_class')) {
    function sanitize_html_class(string $class): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '', $class);
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $key));
    }
}

if (! function_exists('wp_date')) {
    function wp_date(string $format, int $timestamp): string
    {
        return date($format, $timestamp);
    }
}

if (! function_exists('get_temp_dir')) {
    function get_temp_dir(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR;
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.test' . $path;
    }
}

if (! function_exists('add_query_arg')) {
    function add_query_arg(array $arguments, string $url): string
    {
        return $url . '?' . http_build_query($arguments);
    }
}

if (! function_exists('wp_nonce_url')) {
    function wp_nonce_url(string $url, string $action): string
    {
        return $url . '&_wpnonce=' . rawurlencode($action);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}

if (! function_exists('absint')) {
    function absint($value): int
    {
        return abs((int) $value);
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return (bool) ($GLOBALS['apa_test_logged_in'] ?? false);
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action)
    {
        return hash_equals($action, $nonce) ? 1 : false;
    }
}

if (! function_exists('wp_die')) {
    function wp_die(string $message, string $title = '', array $arguments = []): void
    {
        throw new ApaAgadevWpDieException($message, (int) ($arguments['response'] ?? 500));
    }
}

if (! function_exists('acl_flows_api_call')) {
    function acl_flows_api_call(array $arguments): array
    {
        $GLOBALS['apa_test_api_arguments'] = $arguments;

        return $GLOBALS['apa_test_api_response'] ?? [
            'ok' => true,
            'status' => 200,
            'data' => [],
            'headers' => [],
            'set_cookie' => [],
            'error' => null,
        ];
    }
}
