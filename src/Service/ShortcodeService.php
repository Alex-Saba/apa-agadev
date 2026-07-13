<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

/**
 * Renders Maivou APA data through WordPress shortcodes.
 */
final class ShortcodeService
{
    private MaivouDataService $data;

    public function __construct(MaivouDataService $data)
    {
        $this->data = $data;
    }

    /**
     * Registers the public rendering entry points.
     */
    public function register(): void
    {
        add_shortcode('apa_agadev_form', [$this, 'renderAgreementForm']);
        add_shortcode('apa_agadev_agreements', [$this, 'renderAgreements']);
        add_shortcode('apa_agadev_public_lots', [$this, 'renderPublicLots']);
    }

    /**
     * Renders the APA form catalog filtered by the current user's role.
     *
     * @param array<string, mixed> $attributes
     */
    public function renderAgreementForm(array $attributes = []): string
    {
        $attributes = shortcode_atts(['role' => ''], $attributes, 'apa_agadev_form');
        $response = $this->data->getAgreementForm((string) $attributes['role']);

        if (! $response['ok']) {
            return $this->renderError($response);
        }

        $catalog = is_array($response['data']) ? $response['data'] : [];
        $options_response = $this->data->getAgreementOptions($catalog);

        if (! $options_response['ok']) {
            return $this->renderError($options_response);
        }

        $submitted = [];
        $submission = null;

        if ($this->isAgreementSubmission()) {
            $submitted = $this->submittedAgreement();

            if (! $this->hasValidNonce()) {
                $submission = [
                    'ok' => false,
                    'status' => 403,
                    'error' => __('La session du formulaire a expiré. Rechargez la page et réessayez.', 'plugin-apa-agadev'),
                ];
            } else {
                $payload = $this->normalizeAgreement($submitted, $catalog);
                $submission = $this->data->createAgreement($payload);

                if ($submission['ok']) {
                    $submitted = [];
                }
            }
        }

        return $this->renderTemplate('agreement-form', [
            'catalog' => $catalog,
            'remote_options' => is_array($options_response['data']) ? $options_response['data'] : [],
            'submitted' => $submitted,
            'submission' => $submission,
        ]);
    }

    /**
     * Renders agreements visible to the current user.
     */
    public function renderAgreements(): string
    {
        $response = $this->data->getAgreements();

        if (! $response['ok']) {
            return $this->renderError($response);
        }

        return $this->renderTemplate('agreements', [
            'agreements' => is_array($response['data']) ? $response['data'] : [],
        ]);
    }

    /**
     * Renders all visible lots whose Maivou business status is public.
     */
    public function renderPublicLots(): string
    {
        $response = $this->data->getPublicLots();

        if (! $response['ok']) {
            return $this->renderError($response);
        }

        return $this->renderTemplate('public-lots', [
            'lots' => is_array($response['data']) ? $response['data'] : [],
        ]);
    }

    /**
     * Identifies only submissions owned by this shortcode.
     */
    private function isAgreementSubmission(): bool
    {
        return isset($_SERVER['REQUEST_METHOD'], $_POST['apa_agadev_action'])
            && 'POST' === strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])))
            && 'create_agreement' === sanitize_key(wp_unslash((string) $_POST['apa_agadev_action']));
    }

    /**
     * Protects agreement creation against cross-site form submissions.
     */
    private function hasValidNonce(): bool
    {
        $nonce = isset($_POST['apa_agadev_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['apa_agadev_nonce']))
            : '';

        return wp_verify_nonce($nonce, 'apa_agadev_create_agreement') !== false;
    }

    /**
     * Returns the unslashed form tree; field-level normalization happens next.
     *
     * @return array<string, mixed>
     */
    private function submittedAgreement(): array
    {
        $agreement = isset($_POST['agreement']) ? wp_unslash($_POST['agreement']) : [];

        return is_array($agreement) ? $agreement : [];
    }

    /**
     * Builds only fields declared by Maivou's filtered catalog.
     *
     * @param array<string, mixed> $submitted
     * @param array<string, mixed> $catalog
     * @return array<string, mixed>
     */
    private function normalizeAgreement(array $submitted, array $catalog): array
    {
        $payload = [];
        $sections = is_array($catalog['sections'] ?? null) ? $catalog['sections'] : [];

        foreach ($sections as $section_key => $section) {
            if (! is_string($section_key) || ! is_array($section)) {
                continue;
            }

            $raw_section = is_array($submitted[$section_key] ?? null) ? $submitted[$section_key] : [];
            $normalized = $this->normalizeFields($raw_section, $section['fields'] ?? []);
            $subsections = is_array($section['subsections'] ?? null) ? $section['subsections'] : [];

            foreach ($subsections as $subsection_key => $subsection) {
                if (! is_array($subsection)) {
                    continue;
                }

                $normalized += $this->normalizeFields($raw_section, $subsection['fields'] ?? []);

                if (is_string($subsection_key) && is_array($subsection['benefits'] ?? null)) {
                    $benefits = $this->normalizeBenefits(
                        is_array($raw_section[$subsection_key] ?? null) ? $raw_section[$subsection_key] : [],
                        $subsection['benefits']
                    );

                    if ($benefits !== []) {
                        $normalized[$subsection_key] = $benefits;
                    }
                }
            }

            if ($normalized !== []) {
                $payload[$section_key] = $normalized;
            }
        }

        return $payload;
    }

    /**
     * Normalizes a catalog field collection recursively.
     *
     * @param mixed $definitions
     * @return array<string, mixed>
     */
    private function normalizeFields(array $submitted, $definitions): array
    {
        if (! is_array($definitions)) {
            return [];
        }

        $normalized = [];

        foreach ($definitions as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            if ($this->isList($definition)) {
                $definition = is_array($definition[0] ?? null) ? $definition[0] : [];
            }

            $type = (string) ($definition['type'] ?? 'text');
            $raw = $submitted[$key] ?? null;

            if ('repeater' === $type) {
                $rows = [];

                if (is_array($raw)) {
                    foreach ($raw as $row) {
                        if (! is_array($row)) {
                            continue;
                        }

                        $normalized_row = $this->normalizeFields($row, $definition['fields'] ?? []);
                        if (! $this->isEmptyValue($normalized_row)) {
                            $rows[] = $normalized_row;
                        }
                    }
                }

                if ($rows !== []) {
                    $normalized[$key] = $rows;
                }

                continue;
            }

            if (in_array($type, ['file', 'dropzone'], true)) {
                continue;
            }

            if ('checkbox' === $type) {
                $normalized[$key] = '1' === (string) $raw || 1 === $raw || true === $raw;
                continue;
            }

            if ('multiselect' === $type) {
                $values = is_array($raw) ? $raw : [];
                $values = array_values(array_filter(array_map('sanitize_text_field', $values), 'strlen'));

                if ($values !== []) {
                    $normalized[$key] = $values;
                }

                continue;
            }

            if (null === $raw || is_array($raw)) {
                continue;
            }

            $value = 'textarea' === $type
                ? sanitize_textarea_field((string) $raw)
                : sanitize_text_field((string) $raw);

            if ('' !== $value) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Preserves Maivou's nested benefit map and boolean values.
     *
     * @param array<string, mixed> $submitted
     * @param array<string, mixed> $definitions
     * @return array<string, mixed>
     */
    private function normalizeBenefits(array $submitted, array $definitions): array
    {
        $benefits = [];

        foreach ($definitions as $benefit_key => $benefit) {
            if (! is_string($benefit_key) || ! is_array($benefit)) {
                continue;
            }

            $raw = is_array($submitted[$benefit_key] ?? null) ? $submitted[$benefit_key] : [];
            $normalized = $this->normalizeFields($raw, $benefit['fields'] ?? []);

            if (! $this->isEmptyValue($normalized)) {
                $benefits[$benefit_key] = $normalized;
            }
        }

        return $benefits;
    }

    /**
     * PHP 8.0-compatible list detection.
     */
    private function isList(array $value): bool
    {
        return $value !== [] && array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * Treats false checkboxes as empty except when surrounded by other values.
     */
    private function isEmptyValue(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item) && ! $this->isEmptyValue($item)) {
                return false;
            }

            if (! is_array($item) && false !== $item && '' !== $item && null !== $item) {
                return false;
            }
        }

        return true;
    }

    /**
     * Isolates template variables and captures their escaped HTML output.
     *
     * @param array<string, mixed> $variables
     */
    private function renderTemplate(string $template, array $variables): string
    {
        $path = PLUGIN_APA_AGADEV_PATH . 'templates/' . $template . '.php';

        if (! is_readable($path)) {
            return $this->renderError([
                'status' => 500,
                'error' => __('Template APA Agadev introuvable.', 'plugin-apa-agadev'),
            ]);
        }

        wp_enqueue_style('plugin-apa-agadev');

        if ('agreement-form' === $template) {
            wp_enqueue_script('plugin-apa-agadev');
        }
        extract($variables, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }

    /**
     * Displays API failures without replacing them with invented content.
     *
     * @param array<string, mixed> $response
     */
    private function renderError(array $response): string
    {
        wp_enqueue_style('plugin-apa-agadev');

        $message = trim((string) ($response['error'] ?? ''));
        if ('' === $message) {
            $message = __('Impossible de récupérer les données APA depuis Maivou.', 'plugin-apa-agadev');
        }

        return sprintf(
            '<div class="apa-agadev apa-agadev--error" role="alert">%s</div>',
            esc_html($message)
        );
    }
}
