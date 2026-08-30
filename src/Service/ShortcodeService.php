<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

/**
 * Renders Maivou APA data through WordPress shortcodes.
 */
final class ShortcodeService
{
    private MaivouDataService $data;

    private AgreementPresentationService $presentation;

    public function __construct(
        MaivouDataService $data,
        ?AgreementPresentationService $presentation = null
    ) {
        $this->data = $data;
        $this->presentation = $presentation ?? new AgreementPresentationService();
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

        $remoteOptions = is_array($options_response['data']) ? $options_response['data'] : [];

        $submitted = [];
        $submission = null;
        $submissionIntent = '';
        $editingAgreementId = $this->requestedEditingAgreementId();

        if ($this->isAgreementSubmission()) {
            $submitted = $this->submittedAgreement();
            $editingAgreementId = $this->submittedAgreementId();
            $submissionIntent = $this->submissionIntent();

            if (! $this->hasValidNonce()) {
                $submission = $this->submissionError(
                    403,
                    __('La session du formulaire a expiré. Rechargez la page et réessayez.', 'plugin-apa-agadev')
                );
            } elseif ('' === $submissionIntent) {
                $submission = $this->submissionError(
                    400,
                    __('L’action demandée pour ce formulaire APA est invalide.', 'plugin-apa-agadev')
                );
            } else {
                $payload = $this->normalizeAgreement(
                    $submitted,
                    $catalog,
                    $submissionIntent,
                    $editingAgreementId > 0
                );
                $documents = $this->submittedDocuments($catalog);

                if (! $documents['ok']) {
                    $submission = $this->submissionError(422, $documents['error']);
                } elseif ($editingAgreementId > 0 && $documents['files'] !== []) {
                    $submission = $this->submissionError(
                        422,
                        __('Maivou ne permet pas encore de remplacer un document lors de la modification d’un brouillon.', 'plugin-apa-agadev')
                    );
                } elseif ($editingAgreementId > 0) {
                    $draftResponse = $this->data->getAgreement($editingAgreementId);

                    if (! $draftResponse['ok'] || ! is_array($draftResponse['data'])) {
                        $submission = $this->submissionError(
                            (int) ($draftResponse['status'] ?? 502),
                            (string) ($draftResponse['error'] ?? __('Impossible de récupérer ce brouillon APA.', 'plugin-apa-agadev'))
                        );
                    } elseif ('draft' !== strtolower((string) ($draftResponse['data']['status'] ?? ''))) {
                        $submission = $this->submissionError(
                            409,
                            __('Seule une demande APA en brouillon peut être modifiée.', 'plugin-apa-agadev')
                        );
                    } else {
                        $draftValidationError = 'draft' === $submissionIntent
                            ? $this->validateDraftProgress(
                                $submitted,
                                $catalog,
                                $this->submittedCurrentStep(),
                                $documents['manifest'],
                                $this->agreementFormValues($draftResponse['data'], $catalog)
                            )
                            : '';

                        if ('' !== $draftValidationError) {
                            $submission = $this->submissionError(422, $draftValidationError);
                        } else {
                            // File inputs cannot be prefilled by browsers. Preserve their
                            // authenticated Maivou references while updating other fields.
                            $payload = $this->preserveDocumentValues($payload, $draftResponse['data'], $catalog);
                            $submission = $this->data->updateAgreement($editingAgreementId, $payload);
                        }
                    }
                } else {
                    $draftValidationError = 'draft' === $submissionIntent
                        ? $this->validateDraftProgress(
                            $submitted,
                            $catalog,
                            $this->submittedCurrentStep(),
                            $documents['manifest']
                        )
                        : '';

                    if ('' !== $draftValidationError) {
                        $submission = $this->submissionError(422, $draftValidationError);
                    } else {
                        $submission = $this->data->createAgreement(
                            $payload,
                            $documents['files'],
                            $documents['manifest']
                        );
                    }
                }

                if ($submission['ok']) {
                    $submitted = [];
                    $editingAgreementId = 0;
                }
            }
        } elseif ($editingAgreementId > 0) {
            $draftResponse = $this->data->getAgreement($editingAgreementId);

            if (! $draftResponse['ok'] || ! is_array($draftResponse['data'])) {
                $submission = $this->submissionError(
                    (int) ($draftResponse['status'] ?? 502),
                    (string) ($draftResponse['error'] ?? __('Impossible de récupérer ce brouillon APA.', 'plugin-apa-agadev'))
                );
            } elseif ('draft' !== strtolower((string) ($draftResponse['data']['status'] ?? ''))) {
                $submission = $this->submissionError(
                    409,
                    __('Seule une demande APA en brouillon peut être modifiée.', 'plugin-apa-agadev')
                );
                $editingAgreementId = 0;
            } else {
                $submitted = $this->agreementFormValues($draftResponse['data'], $catalog);
            }
        }

        return $this->renderTemplate('agreement-form', [
            'catalog' => $catalog,
            'remote_options' => $remoteOptions,
            'submitted' => $submitted,
            'submission' => $submission,
            'submission_intent' => $submissionIntent,
            'editing_agreement_id' => $editingAgreementId,
            'layout' => $layout,
        ]);
    }

    /**
     * Renders agreements visible to the current user.
     */
    public function renderAgreements(): string
    {
        $newAgreementRequest = $this->isNewAgreementRequest();
        $editingAgreementId = $this->requestedEditingAgreementId();

        // Process a possible creation first so the refreshed list can include it.
        $form = $this->renderAgreementForm(['layout' => 'modal']);
        $agreementId = $this->requestedAgreementId();
        $agreements = [];
        $agreements_error = '';
        $detail = '';

        if ($agreementId > 0) {
            $detailResponse = $this->data->getAgreement($agreementId);

            if (! $detailResponse['ok'] || ! is_array($detailResponse['data'])) {
                $agreements_error = trim((string) ($detailResponse['error'] ?? ''));
                if ('' === $agreements_error) {
                    $agreements_error = __('Impossible de récupérer cette demande APA depuis Maivou.', 'plugin-apa-agadev');
                }
            } else {
                $detail = $this->renderAgreementDetail($detailResponse['data']);
            }
        } else {
            $response = $this->data->getAgreements();

            if ($response['ok'] && is_array($response['data'])) {
                $agreements = $response['data'];
            } else {
                $agreements_error = trim((string) ($response['error'] ?? ''));
                if ('' === $agreements_error) {
                    $agreements_error = __('Impossible de récupérer les agréments depuis Maivou.', 'plugin-apa-agadev');
                }
            }
        }

        return $this->renderTemplate('agreements', [
            'agreements' => $agreements,
            'agreements_error' => $agreements_error,
            'form' => $form,
            'open_modal' => $this->isAgreementSubmission() || $newAgreementRequest || $editingAgreementId > 0,
            'editing_agreement_id' => $editingAgreementId,
            'agreement_detail' => $detail,
        ]);
    }

    /** @param array<string, mixed> $agreement */
    private function renderAgreementDetail(array $agreement): string
    {
        $agreementId = (int) ($agreement['id'] ?? 0);
        $backUrl = remove_query_arg(['apa_agadev_agreement', 'agreement_page']);

        try {
            $detail = $this->presentation->present($agreement);
        } catch (\UnexpectedValueException $exception) {
            $this->logInvalidPresentation($agreementId, $exception);

            return $this->renderPresentationUnavailable();
        }

        return $this->renderTemplate('agreement-detail', [
            'agreement_detail' => $detail,
            'back_url' => $backUrl,
            'download_url' => AgreementPdfService::downloadUrl($agreementId),
        ]);
    }

    private function renderPresentationUnavailable(): string
    {
        return '<div class="acl_shortcode_notice acl_shortcode_notice--error acl_shortcode_apa_error acl_shortcode_div" role="alert">'
            . esc_html__('La présentation détaillée de cette demande APA est indisponible.', 'plugin-apa-agadev')
            . '</div>';
    }

    private function logInvalidPresentation(int $agreementId, \UnexpectedValueException $exception): void
    {
        // Only structural context is logged; the agreement payload stays private.
        error_log(sprintf(
            '[APA Agadev] Invalid agreement presentation for agreement %d: %s',
            $agreementId,
            $exception->getMessage()
        ));
    }

    /**
     * Reads the agreement selected in the user's list without accepting arrays.
     */
    private function requestedAgreementId(): int
    {
        if (! isset($_GET['apa_agadev_agreement']) || ! is_scalar($_GET['apa_agadev_agreement'])) {
            return 0;
        }

        return absint(wp_unslash((string) $_GET['apa_agadev_agreement']));
    }

    /**
     * Reads the draft selected for editing from the agreement list.
     */
    private function requestedEditingAgreementId(): int
    {
        // An explicit creation request must never inherit a stale editing query.
        if ($this->isNewAgreementRequest()) {
            return 0;
        }

        if (! isset($_GET['apa_agadev_edit_agreement']) || ! is_scalar($_GET['apa_agadev_edit_agreement'])) {
            return 0;
        }

        return absint(wp_unslash((string) $_GET['apa_agadev_edit_agreement']));
    }

    /**
     * Identifies an explicit request to start with a blank agreement form.
     */
    private function isNewAgreementRequest(): bool
    {
        if (! isset($_GET['apa_agadev_new_agreement']) || ! is_scalar($_GET['apa_agadev_new_agreement'])) {
            return false;
        }

        return '1' === sanitize_text_field(wp_unslash((string) $_GET['apa_agadev_new_agreement']));
    }

    /**
     * Reads the draft identifier posted by the plugin form.
     */
    private function submittedAgreementId(): int
    {
        if (! isset($_POST['apa_agadev_agreement_id']) || ! is_scalar($_POST['apa_agadev_agreement_id'])) {
            return 0;
        }

        return absint(wp_unslash((string) $_POST['apa_agadev_agreement_id']));
    }

    /**
     * Identifies only submissions owned by this shortcode.
     */
    private function isAgreementSubmission(): bool
    {
        return isset($_SERVER['REQUEST_METHOD'], $_POST['apa_agadev_action'])
            && 'POST' === strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])))
            && in_array(
                sanitize_key(wp_unslash((string) $_POST['apa_agadev_action'])),
                ['create_agreement', 'save_agreement'],
                true
            );
    }

    /**
     * Accepts only the two workflow decisions exposed by the form.
     */
    private function submissionIntent(): string
    {
        if (! isset($_POST['apa_agadev_submission_intent'])) {
            // Backward compatibility for a form cached before draft support.
            return 'pending';
        }

        if (! is_scalar($_POST['apa_agadev_submission_intent'])) {
            return '';
        }

        $intent = sanitize_key(wp_unslash((string) $_POST['apa_agadev_submission_intent']));

        return in_array($intent, ['draft', 'pending'], true) ? $intent : '';
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
     * Returns the visible workflow step from which the draft was saved.
     */
    private function submittedCurrentStep(): int
    {
        if (! isset($_POST['apa_agadev_current_step']) || ! is_scalar($_POST['apa_agadev_current_step'])) {
            return 0;
        }

        return max(0, absint(wp_unslash((string) $_POST['apa_agadev_current_step'])));
    }

    /**
     * A draft may omit future sections, but every visited section must be valid.
     *
     * @param array<string, mixed> $submitted
     * @param array<string, mixed> $catalog
     * @param list<array{path:string,multiple:bool}> $documentManifest
     * @param array<string, mixed> $existing
     */
    private function validateDraftProgress(
        array $submitted,
        array $catalog,
        int $currentStep,
        array $documentManifest = [],
        array $existing = []
    ): string {
        $steps = [];
        $sections = is_array($catalog['sections'] ?? null) ? $catalog['sections'] : [];

        foreach ($sections as $sectionKey => $section) {
            if (! is_string($sectionKey) || ! is_array($section)) {
                continue;
            }

            $fields = is_array($section['fields'] ?? null) ? $section['fields'] : [];
            $subsections = is_array($section['subsections'] ?? null) ? $section['subsections'] : [];

            if ([] === $fields && [] === $subsections) {
                continue;
            }

            $steps[] = [
                'key' => $sectionKey,
                'label' => (string) ($section['title'] ?? $sectionKey),
                'fields' => $fields,
                'subsections' => $subsections,
            ];
        }

        if ([] === $steps) {
            return '';
        }

        $documentPaths = [];
        foreach ($documentManifest as $document) {
            if (is_array($document) && is_scalar($document['path'] ?? null)) {
                $documentPaths[(string) $document['path']] = true;
            }
        }

        $lastStep = min($currentStep, count($steps) - 1);

        for ($stepIndex = 0; $stepIndex <= $lastStep; $stepIndex++) {
            $step = $steps[$stepIndex];
            $sectionKey = (string) $step['key'];
            $rawSection = is_array($submitted[$sectionKey] ?? null) ? $submitted[$sectionKey] : [];
            $existingSection = is_array($existing[$sectionKey] ?? null) ? $existing[$sectionKey] : [];
            $missingLabel = $this->requiredFieldMissing(
                $rawSection,
                $existingSection,
                $step['fields'],
                [$sectionKey],
                $documentPaths
            );

            foreach ($step['subsections'] as $subsectionKey => $subsection) {
                if ('' !== $missingLabel || ! is_array($subsection)) {
                    break;
                }

                $missingLabel = $this->requiredFieldMissing(
                    $rawSection,
                    $existingSection,
                    $subsection['fields'] ?? [],
                    [$sectionKey],
                    $documentPaths
                );

                if ('' !== $missingLabel || ! is_string($subsectionKey)) {
                    continue;
                }

                $rawBenefits = is_array($rawSection[$subsectionKey] ?? null) ? $rawSection[$subsectionKey] : [];
                $existingBenefits = is_array($existingSection[$subsectionKey] ?? null)
                    ? $existingSection[$subsectionKey]
                    : [];

                foreach ((array) ($subsection['benefits'] ?? []) as $benefitKey => $benefit) {
                    if (! is_string($benefitKey) || ! is_array($benefit)) {
                        continue;
                    }

                    $missingLabel = $this->requiredFieldMissing(
                        is_array($rawBenefits[$benefitKey] ?? null) ? $rawBenefits[$benefitKey] : [],
                        is_array($existingBenefits[$benefitKey] ?? null) ? $existingBenefits[$benefitKey] : [],
                        $benefit['fields'] ?? [],
                        [$sectionKey, $subsectionKey, $benefitKey],
                        $documentPaths
                    );

                    if ('' !== $missingLabel) {
                        break;
                    }
                }
            }

            if ('' !== $missingLabel) {
                return sprintf(
                    /* translators: 1: section title, 2: required field label */
                    __('Complétez les champs obligatoires de la section « %1$s » avant d’enregistrer le brouillon. Champ manquant : %2$s.', 'plugin-apa-agadev'),
                    (string) $step['label'],
                    $missingLabel
                );
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $submitted
     * @param array<string, mixed> $existing
     * @param mixed $definitions
     * @param list<string> $path
     * @param array<string, bool> $documentPaths
     */
    private function requiredFieldMissing(
        array $submitted,
        array $existing,
        $definitions,
        array $path,
        array $documentPaths
    ): string {
        if (! is_array($definitions)) {
            return '';
        }

        foreach ($definitions as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            if ($this->isList($definition)) {
                $definition = is_array($definition[0] ?? null) ? $definition[0] : [];
            }

            $type = (string) ($definition['type'] ?? 'text');
            $label = (string) ($definition['label'] ?? $key);
            $value = $submitted[$key] ?? null;
            $existingValue = $existing[$key] ?? null;
            $fieldPath = [...$path, $key];

            if ('repeater' === $type) {
                $rows = is_array($value) ? $value : [];
                $existingRows = is_array($existingValue) ? $existingValue : [];
                $meaningfulRows = array_filter($rows, fn ($row): bool => $this->hasMeaningfulValue($row));

                if (! empty($definition['required']) && [] === $meaningfulRows) {
                    return $label;
                }

                foreach ($rows as $rowIndex => $row) {
                    if (! is_array($row) || (! $this->hasMeaningfulValue($row) && empty($definition['required']))) {
                        continue;
                    }

                    $missing = $this->requiredFieldMissing(
                        $row,
                        is_array($existingRows[$rowIndex] ?? null) ? $existingRows[$rowIndex] : [],
                        $definition['fields'] ?? [],
                        [...$fieldPath, (string) $rowIndex],
                        $documentPaths
                    );

                    if ('' !== $missing) {
                        return $missing;
                    }
                }

                continue;
            }

            if (empty($definition['required'])) {
                continue;
            }

            if (in_array($type, ['file', 'dropzone'], true)) {
                $canonicalPath = $this->canonicalDocumentPath(implode('.', $fieldPath));
                if (! $this->hasMeaningfulValue($value)
                    && ! $this->hasMeaningfulValue($existingValue)
                    && empty($documentPaths[$canonicalPath])) {
                    return $label;
                }
                continue;
            }

            if ('checkbox' === $type) {
                if (! in_array($value, [true, 1, '1'], true)) {
                    return $label;
                }
                continue;
            }

            if (! $this->hasMeaningfulValue($value)) {
                return $label;
            }
        }

        return '';
    }

    /**
     * Treats nested arrays, false checkboxes and whitespace-only strings as empty.
     *
     * @param mixed $value
     */
    private function hasMeaningfulValue($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasMeaningfulValue($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        return is_scalar($value) && '' !== trim((string) $value) && '0' !== trim((string) $value);
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
            $catalog_path = $this->agreementFieldPath($submitted_path);
            $multiple = $this->allowedFilePath($catalog_path, $allowed_paths);

            if (null === $multiple) {
                return $this->documentError(__('Un document cible un champ absent du formulaire autorisé.', 'plugin-apa-agadev'));
            }

            // Validate the catalog-shaped browser path before translating the
            // providers repeater to Maivou's root-list persistence contract.
            $path = $this->canonicalDocumentPath($catalog_path);

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
     * Translates form-only wrappers to the persisted Maivou document path.
     */
    private function canonicalDocumentPath(string $catalogPath): string
    {
        if (preg_match('/^providers\.providers\.(\d+)\.(.+)$/', $catalogPath, $matches)) {
            return 'providers.' . $matches[1] . '.' . $matches[2];
        }

        return $catalogPath;
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
     * Builds the response shape consumed by the agreement form template.
     *
     * @return array{ok:false,status:int,data:null,headers:array,set_cookie:array,error:string}
     */
    private function submissionError(int $status, string $message): array
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

    /**
     * Keeps only role-filtered form sections from an authorized draft payload.
     *
     * @param array<string, mixed> $agreement
     * @param array<string, mixed> $catalog
     * @return array<string, mixed>
     */
    private function agreementFormValues(array $agreement, array $catalog): array
    {
        $values = [];
        $sections = is_array($catalog['sections'] ?? null) ? $catalog['sections'] : [];

        foreach ($sections as $sectionKey => $section) {
            if (is_string($sectionKey) && is_array($section) && is_array($agreement[$sectionKey] ?? null)) {
                // Maivou stores providers directly as a root list, while the
                // catalog-driven form nests that repeater inside its section.
                $values[$sectionKey] = 'providers' === $sectionKey
                    ? ['providers' => $agreement[$sectionKey]]
                    : $agreement[$sectionKey];
            }
        }

        return $values;
    }

    /**
     * Preserves stored file references that browsers cannot place back into a
     * file input when a draft is reopened.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $catalog
     * @return array<string, mixed>
     */
    private function preserveDocumentValues(array $payload, array $existing, array $catalog): array
    {
        $sections = is_array($catalog['sections'] ?? null) ? $catalog['sections'] : [];

        foreach ($sections as $sectionKey => $section) {
            if (! is_string($sectionKey) || ! is_array($section) || ! is_array($payload[$sectionKey] ?? null)) {
                continue;
            }

            $existingSection = is_array($existing[$sectionKey] ?? null) ? $existing[$sectionKey] : [];

            if ('providers' === $sectionKey) {
                // Restore the form-shaped wrapper temporarily so documents
                // nested in provider rows can be preserved before the API PUT.
                $providerPayload = ['providers' => $payload[$sectionKey]];
                $providerExisting = ['providers' => $existingSection];

                foreach ((array) ($section['subsections'] ?? []) as $subsection) {
                    if (is_array($subsection)) {
                        $providerPayload = $this->preserveDefinedDocuments(
                            $providerPayload,
                            $providerExisting,
                            $subsection['fields'] ?? []
                        );
                    }
                }

                $payload[$sectionKey] = is_array($providerPayload['providers'] ?? null)
                    ? $providerPayload['providers']
                    : $payload[$sectionKey];

                continue;
            }

            $payload[$sectionKey] = $this->preserveDefinedDocuments(
                $payload[$sectionKey],
                $existingSection,
                $section['fields'] ?? []
            );

            foreach ((array) ($section['subsections'] ?? []) as $subsectionKey => $subsection) {
                if (! is_array($subsection)) {
                    continue;
                }

                $payload[$sectionKey] = $this->preserveDefinedDocuments(
                    $payload[$sectionKey],
                    $existingSection,
                    $subsection['fields'] ?? []
                );

                if (! is_string($subsectionKey) || ! is_array($subsection['benefits'] ?? null)) {
                    continue;
                }

                foreach ($subsection['benefits'] as $benefitKey => $benefit) {
                    if (
                        ! is_string($benefitKey)
                        || ! is_array($benefit)
                        || ! is_array($payload[$sectionKey][$subsectionKey][$benefitKey] ?? null)
                    ) {
                        continue;
                    }

                    $existingBenefit = is_array($existingSection[$subsectionKey][$benefitKey] ?? null)
                        ? $existingSection[$subsectionKey][$benefitKey]
                        : [];
                    $payload[$sectionKey][$subsectionKey][$benefitKey] = $this->preserveDefinedDocuments(
                        $payload[$sectionKey][$subsectionKey][$benefitKey],
                        $existingBenefit,
                        $benefit['fields'] ?? []
                    );
                }
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $existing
     * @param mixed $definitions
     * @return array<string, mixed>
     */
    private function preserveDefinedDocuments(array $payload, array $existing, $definitions): array
    {
        if (! is_array($definitions)) {
            return $payload;
        }

        foreach ($definitions as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            if ($this->isList($definition)) {
                $definition = is_array($definition[0] ?? null) ? $definition[0] : [];
            }

            $type = (string) ($definition['type'] ?? 'text');

            if (in_array($type, ['file', 'dropzone'], true)) {
                if (! array_key_exists($key, $payload) && array_key_exists($key, $existing)) {
                    $payload[$key] = $existing[$key];
                }

                continue;
            }

            if ('repeater' !== $type || ! is_array($payload[$key] ?? null) || ! is_array($existing[$key] ?? null)) {
                continue;
            }

            foreach ($payload[$key] as $rowIndex => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $existingRow = is_array($existing[$key][$rowIndex] ?? null) ? $existing[$key][$rowIndex] : [];
                $payload[$key][$rowIndex] = $this->preserveDefinedDocuments(
                    $row,
                    $existingRow,
                    $definition['fields'] ?? []
                );
            }
        }

        return $payload;
    }

    /**
     * Builds fields declared by Maivou's filtered catalog for one workflow state.
     *
     * @param array<string, mixed> $submitted
     * @param array<string, mixed> $catalog
     * @return array<string, mixed>
     */
    private function normalizeAgreement(
        array $submitted,
        array $catalog,
        string $status,
        bool $includeEmpty = false
    ): array
    {
        $payload = [];
        $sections = is_array($catalog['sections'] ?? null) ? $catalog['sections'] : [];

        foreach ($sections as $section_key => $section) {
            if (! is_string($section_key) || ! is_array($section)) {
                continue;
            }

            $raw_section = is_array($submitted[$section_key] ?? null) ? $submitted[$section_key] : [];
            $normalized = $this->normalizeFields($raw_section, $section['fields'] ?? [], $includeEmpty);
            $subsections = is_array($section['subsections'] ?? null) ? $section['subsections'] : [];

            foreach ($subsections as $subsection_key => $subsection) {
                if (! is_array($subsection)) {
                    continue;
                }

                $normalized += $this->normalizeFields($raw_section, $subsection['fields'] ?? [], $includeEmpty);

                if (is_string($subsection_key) && is_array($subsection['benefits'] ?? null)) {
                    $benefits = $this->normalizeBenefits(
                        is_array($raw_section[$subsection_key] ?? null) ? $raw_section[$subsection_key] : [],
                        $subsection['benefits'],
                        $includeEmpty
                    );

                    if ($benefits !== []) {
                        $normalized[$subsection_key] = $benefits;
                    }
                }
            }

            if ($normalized !== []) {
                // The Maivou catalog groups the providers repeater inside a
                // providers section, while the API contract expects the rows
                // directly at the root `providers` key.
                if ('providers' === $section_key && is_array($normalized['providers'] ?? null)) {
                    $payload[$section_key] = $normalized['providers'];
                } else {
                    $payload[$section_key] = $normalized;
                }
            }
        }

        // The caller already reduced the browser input to the explicit allowlist.
        $payload['status'] = $status;

        return $payload;
    }

    /**
     * Normalizes a catalog field collection recursively.
     *
     * @param mixed $definitions
     * @return array<string, mixed>
     */
    private function normalizeFields(array $submitted, $definitions, bool $includeEmpty = false): array
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

                        $normalized_row = $this->normalizeFields($row, $definition['fields'] ?? [], $includeEmpty);
                        if (! $this->isEmptyValue($normalized_row)) {
                            $rows[] = $normalized_row;
                        }
                    }
                }

                if ($rows !== []) {
                    $normalized[$key] = $rows;
                } elseif ($includeEmpty && array_key_exists($key, $submitted)) {
                    $normalized[$key] = [];
                }

                continue;
            }

            if (in_array($type, ['file', 'dropzone'], true)) {
                continue;
            }

            if ('number' === $type) {
                if (! is_numeric($raw)) {
                    if ($includeEmpty && array_key_exists($key, $submitted)) {
                        $normalized[$key] = null;
                    }
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
                } elseif ($includeEmpty && array_key_exists($key, $submitted)) {
                    $normalized[$key] = [];
                }

                continue;
            }

            if (null === $raw || is_array($raw)) {
                if ($includeEmpty && array_key_exists($key, $submitted) && null === $raw) {
                    $normalized[$key] = null;
                }
                continue;
            }

            $value = 'textarea' === $type
                ? sanitize_textarea_field((string) $raw)
                : sanitize_text_field((string) $raw);

            if ('' !== $value) {
                $normalized[$key] = $value;
            } elseif ($includeEmpty) {
                $normalized[$key] = null;
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
    private function normalizeBenefits(array $submitted, array $definitions, bool $includeEmpty = false): array
    {
        $benefits = [];

        foreach ($definitions as $benefit_key => $benefit) {
            if (! is_string($benefit_key) || ! is_array($benefit)) {
                continue;
            }

            $raw = is_array($submitted[$benefit_key] ?? null) ? $submitted[$benefit_key] : [];
            $normalized = $this->normalizeFields($raw, $benefit['fields'] ?? [], $includeEmpty);

            if (! $this->isEmptyValue($normalized)) {
                $benefits[$benefit_key] = $normalized;
            } elseif ($includeEmpty && array_key_exists($benefit_key, $submitted)) {
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
