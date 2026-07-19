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
    }

    /**
     * Renders the APA form catalog filtered by the current user's role.
     *
     * @param array<string, mixed> $attributes
     */
    public function renderAgreementForm(array $attributes = []): string
    {
        $attributes = shortcode_atts([
            'role' => '',
            'layout' => 'full',
        ], $attributes, 'apa_agadev_form');
        $layout = 'modal' === sanitize_key((string) $attributes['layout']) ? 'modal' : 'full';
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
                $documents = $this->submittedDocuments($catalog);

                if (! $documents['ok']) {
                    $submission = [
                        'ok' => false,
                        'status' => 422,
                        'data' => null,
                        'headers' => [],
                        'set_cookie' => [],
                        'error' => $documents['error'],
                    ];
                } else {
                    $submission = $this->data->createAgreement(
                        $payload,
                        $documents['files'],
                        $documents['manifest']
                    );
                }

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
            'layout' => $layout,
        ]);
    }

    /**
     * Renders agreements visible to the current user.
     */
    public function renderAgreements(): string
    {
        // Process a possible creation first so the refreshed list can include it.
        $form = $this->renderAgreementForm(['layout' => 'modal']);
        $response = $this->data->getAgreements();
        $agreements_error = $response['ok'] ? '' : trim((string) ($response['error'] ?? ''));

        if (! $response['ok'] && '' === $agreements_error) {
            $agreements_error = __('Impossible de récupérer les agréments depuis Maivou.', 'plugin-apa-agadev');
        }

        return $this->renderTemplate('agreements', [
            'agreements' => $response['ok'] && is_array($response['data']) ? $response['data'] : [],
            'agreements_error' => $agreements_error,
            'form' => $form,
            'open_modal' => $this->isAgreementSubmission(),
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
     * Validates uploaded documents against the role-filtered catalog and
     * prepares the flat multipart contract expected by the Bridge.
     *
     * @param array<string, mixed> $catalog
     * @return array{ok:bool,files:array<string,array{path:string,filename:string,mime:string}>,manifest:list<array{path:string,multiple:bool}>,error:string}
     */
    private function submittedDocuments(array $catalog): array
    {
        $allowed_paths = $this->allowedFilePaths($catalog);
        $files = [];
        $manifest = [];
        $allowed_mimes = [
            'pdf' => 'application/pdf',
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
        ];

        foreach ($_FILES as $upload_key => $upload) {
            if (! is_string($upload_key) || ! preg_match('/^apa_agadev_document_(\d+)$/', $upload_key, $matches)) {
                continue;
            }

            $index = (int) $matches[1];
            $path_key = 'apa_agadev_document_path_' . $index;
            $submitted_path = isset($_POST[$path_key])
                ? sanitize_text_field(wp_unslash((string) $_POST[$path_key]))
                : '';
            $path = $this->agreementFieldPath($submitted_path);
            $multiple = $this->allowedFilePath($path, $allowed_paths);

            if (null === $multiple) {
                return $this->documentError(__('Un document cible un champ absent du formulaire autorisé.', 'plugin-apa-agadev'));
            }

            if (! is_array($upload)) {
                return $this->documentError(__('La structure d’un document téléversé est invalide.', 'plugin-apa-agadev'));
            }

            $names = is_array($upload['name'] ?? null) ? $upload['name'] : [$upload['name'] ?? ''];
            $temporary_paths = is_array($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : [$upload['tmp_name'] ?? ''];
            $errors = is_array($upload['error'] ?? null) ? $upload['error'] : [$upload['error'] ?? UPLOAD_ERR_NO_FILE];
            $sizes = is_array($upload['size'] ?? null) ? $upload['size'] : [$upload['size'] ?? 0];

            foreach ($names as $file_index => $original_name) {
                $error = (int) ($errors[$file_index] ?? UPLOAD_ERR_NO_FILE);

                if (UPLOAD_ERR_NO_FILE === $error) {
                    continue;
                }

                if (UPLOAD_ERR_OK !== $error) {
                    return $this->documentError(__('Le téléversement d’un document a échoué.', 'plugin-apa-agadev'));
                }

                $temporary_path = (string) ($temporary_paths[$file_index] ?? '');
                $filename = sanitize_file_name((string) $original_name);
                $size = (int) ($sizes[$file_index] ?? 0);

                if ($temporary_path === '' || ! is_uploaded_file($temporary_path)) {
                    return $this->documentError(__('Un document téléversé n’est pas valide.', 'plugin-apa-agadev'));
                }

                if ($size <= 0 || $size > 5 * MB_IN_BYTES) {
                    return $this->documentError(__('Chaque document doit avoir une taille maximale de 5 Mo.', 'plugin-apa-agadev'));
                }

                $checked_type = wp_check_filetype_and_ext($temporary_path, $filename, $allowed_mimes);
                $mime = is_array($checked_type) ? (string) ($checked_type['type'] ?? '') : '';

                if ($mime === '') {
                    return $this->documentError(__('Seuls les documents PDF, JPG et PNG sont autorisés.', 'plugin-apa-agadev'));
                }

                if (count($manifest) >= 10) {
                    return $this->documentError(__('Une demande APA ne peut pas contenir plus de 10 documents.', 'plugin-apa-agadev'));
                }

                $multipart_key = 'documents[' . count($manifest) . ']';
                $files[$multipart_key] = [
                    'path' => $temporary_path,
                    'filename' => $filename,
                    'mime' => $mime,
                ];
                $manifest[] = [
                    'path' => $path,
                    'multiple' => $multiple,
                ];
            }
        }

        return [
            'ok' => true,
            'files' => $files,
            'manifest' => $manifest,
            'error' => '',
        ];
    }

    /**
     * Builds file path patterns from only the catalog returned for the user.
     *
     * @param array<string, mixed> $catalog
     * @return array<string, bool>
     */
    private function allowedFilePaths(array $catalog): array
    {
        $paths = [];
        $sections = is_array($catalog['sections'] ?? null) ? $catalog['sections'] : [];

        foreach ($sections as $section_key => $section) {
            if (! is_string($section_key) || ! is_array($section)) {
                continue;
            }

            $this->collectFilePaths($section['fields'] ?? [], [$section_key], $paths);

            foreach ((array) ($section['subsections'] ?? []) as $subsection_key => $subsection) {
                if (! is_array($subsection)) {
                    continue;
                }

                $this->collectFilePaths($subsection['fields'] ?? [], [$section_key], $paths);

                if (! is_string($subsection_key)) {
                    continue;
                }

                foreach ((array) ($subsection['benefits'] ?? []) as $benefit_key => $benefit) {
                    if (is_string($benefit_key) && is_array($benefit)) {
                        $this->collectFilePaths(
                            $benefit['fields'] ?? [],
                            [$section_key, $subsection_key, $benefit_key],
                            $paths
                        );
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * @param mixed $definitions
     * @param list<string> $prefix
     * @param array<string, bool> $paths
     */
    private function collectFilePaths($definitions, array $prefix, array &$paths): void
    {
        if (! is_array($definitions)) {
            return;
        }

        foreach ($definitions as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            if ($this->isList($definition)) {
                $definition = is_array($definition[0] ?? null) ? $definition[0] : [];
            }

            $type = (string) ($definition['type'] ?? 'text');
            $path = [...$prefix, $key];

            if ('repeater' === $type) {
                $this->collectFilePaths($definition['fields'] ?? [], [...$path, '*'], $paths);
            } elseif (in_array($type, ['file', 'dropzone'], true)) {
                $paths[implode('.', $path)] = 'dropzone' === $type;
            }
        }
    }

    /**
     * Converts an HTML field name to the dotted path stored in Maivou.
     */
    private function agreementFieldPath(string $name): string
    {
        $path = str_replace([']', '['], ['', '.'], $name);
        $path = preg_replace('/^agreement\./', '', $path);

        return is_string($path) ? trim($path, '.') : '';
    }

    /**
     * Returns whether the matched catalog field supports multiple files.
     *
     * @param array<string, bool> $allowed_paths
     */
    private function allowedFilePath(string $path, array $allowed_paths): ?bool
    {
        $segments = explode('.', $path);

        foreach ($allowed_paths as $pattern => $multiple) {
            $pattern_segments = explode('.', $pattern);

            if (count($segments) !== count($pattern_segments)) {
                continue;
            }

            foreach ($pattern_segments as $index => $pattern_segment) {
                if ('*' === $pattern_segment ? ! ctype_digit($segments[$index]) : $pattern_segment !== $segments[$index]) {
                    continue 2;
                }
            }

            return $multiple;
        }

        return null;
    }

    /**
     * @return array{ok:false,files:array,manifest:array,error:string}
     */
    private function documentError(string $message): array
    {
        return [
            'ok' => false,
            'files' => [],
            'manifest' => [],
            'error' => $message,
        ];
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

            if ('number' === $type) {
                if (! is_numeric($raw)) {
                    continue;
                }

                $numeric_value = (float) $raw;
                $normalized[$key] = floor($numeric_value) === $numeric_value
                    ? (int) $numeric_value
                    : $numeric_value;
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

        if (in_array($template, ['agreement-form', 'agreements'], true)) {
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
            '<div class="acl_shortcode_notice acl_shortcode_notice--error acl_shortcode_apa_error acl_shortcode_div" role="alert">%s</div>',
            esc_html($message)
        );
    }
}
