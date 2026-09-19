<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PluginApaAgadev\Service\MaivouDataService;
use PluginApaAgadev\Service\ShortcodeService;

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($value): string
    {
        $encoded = json_encode($value);

        return is_string($encoded) ? $encoded : '';
    }
}

final class AgreementSubmissionTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        unset($GLOBALS['apa_test_api_arguments'], $GLOBALS['apa_test_api_response']);
    }

    public function testNewAgreementRequestOverridesAStaleDraftQuery(): void
    {
        $_GET = [
            'apa_agadev_edit_agreement' => '42',
            'apa_agadev_new_agreement' => '1',
        ];
        $service = new ShortcodeService(new MaivouDataService());
        $requestedEditingAgreementId = Closure::bind(
            static fn (ShortcodeService $target): int => $target->requestedEditingAgreementId(),
            null,
            ShortcodeService::class
        );

        self::assertSame(0, $requestedEditingAgreementId($service));
    }

    public function testDraftRequiresAnExplicitEditingQueryToBeSelected(): void
    {
        $_GET = ['apa_agadev_edit_agreement' => '42'];
        $service = new ShortcodeService(new MaivouDataService());
        $requestedEditingAgreementId = Closure::bind(
            static fn (ShortcodeService $target): int => $target->requestedEditingAgreementId(),
            null,
            ShortcodeService::class
        );

        self::assertSame(42, $requestedEditingAgreementId($service));

        $_GET = [];

        self::assertSame(0, $requestedEditingAgreementId($service));
    }

    public function testWordPressSubmissionUsesTheValidatedPendingIntent(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $normalize = Closure::bind(
            static fn (ShortcodeService $target, array $submitted, array $catalog, string $status, bool $includeEmpty = false): array =>
                $target->normalizeAgreement($submitted, $catalog, $status, $includeEmpty),
            null,
            ShortcodeService::class
        );

        $catalog = [
            'sections' => [
                'genetic_resources' => [
                    'fields' => [
                        'resources' => [
                            'type' => 'repeater',
                            'fields' => [
                                'product' => ['type' => 'select'],
                                'quantity' => ['type' => 'number'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $submitted = [
            // A browser-provided workflow status must never override the plugin decision.
            'status' => 'draft',
            'genetic_resources' => [
                'resources' => [[
                    'product' => 'product-uuid',
                    'quantity' => '15',
                ]],
            ],
        ];

        /** @var array<string, mixed> $payload */
        $payload = $normalize($service, $submitted, $catalog, 'pending');

        self::assertSame('pending', $payload['status']);
        self::assertSame([
            ['product' => 'product-uuid', 'quantity' => 15],
        ], $payload['genetic_resources']['resources']);
    }

    public function testDraftRequiresVisitedSectionsButAllowsFutureSectionsToRemainEmpty(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $validate = Closure::bind(
            static fn (
                ShortcodeService $target,
                array $submitted,
                array $catalog,
                int $currentStep
            ): string => $target->validateDraftProgress($submitted, $catalog, $currentStep),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'first' => [
                'title' => 'Première section',
                'fields' => ['name' => ['type' => 'text', 'label' => 'Nom', 'required' => true]],
            ],
            'second' => [
                'title' => 'Deuxième section',
                'fields' => ['email' => ['type' => 'email', 'label' => 'Email', 'required' => true]],
            ],
        ]];

        self::assertSame('', $validate($service, ['first' => ['name' => 'Ada']], $catalog, 0));

        $error = $validate($service, ['first' => ['name' => 'Ada']], $catalog, 1);
        self::assertStringContainsString('Deuxième section', $error);
        self::assertStringContainsString('Email', $error);
    }

    public function testDraftValidatesRequiredRepeaterChildrenAndMultiselects(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $validate = Closure::bind(
            static fn (ShortcodeService $target, array $submitted, array $catalog): string =>
                $target->validateDraftProgress($submitted, $catalog, 0),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'providers' => [
                'title' => 'Fournisseurs',
                'fields' => [
                    'providers' => [
                        'type' => 'repeater',
                        'required' => true,
                        'label' => 'Fournisseurs',
                        'fields' => [
                            'name' => ['type' => 'text', 'label' => 'Nom', 'required' => true],
                            'roles' => ['type' => 'multiselect', 'label' => 'Rôles', 'required' => true],
                        ],
                    ],
                ],
            ],
        ]];

        self::assertStringContainsString('Nom', $validate($service, [
            'providers' => ['providers' => [['name' => '', 'roles' => ['collector']]]],
        ], $catalog));
        self::assertStringContainsString('Rôles', $validate($service, [
            'providers' => ['providers' => [['name' => 'Association', 'roles' => []]]],
        ], $catalog));
        self::assertSame('', $validate($service, [
            'providers' => ['providers' => [['name' => 'Association', 'roles' => ['collector']]]],
        ], $catalog));
    }

    public function testDraftAcceptsUploadedOrPreviouslyStoredRequiredDocuments(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $validate = Closure::bind(
            static fn (
                ShortcodeService $target,
                array $submitted,
                array $catalog,
                array $manifest = [],
                array $existing = []
            ): string => $target->validateDraftProgress($submitted, $catalog, 0, $manifest, $existing),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'providers' => [
                'title' => 'Fournisseurs',
                'fields' => [
                    'providers' => [
                        'type' => 'repeater',
                        'required' => true,
                        'fields' => [
                            'name' => ['type' => 'text', 'required' => true],
                            'identification' => ['type' => 'file', 'label' => 'Identification', 'required' => true],
                        ],
                    ],
                ],
            ],
        ]];
        $submitted = ['providers' => ['providers' => [['name' => 'Association']]]];

        self::assertStringContainsString('Identification', $validate($service, $submitted, $catalog));
        self::assertSame('', $validate($service, $submitted, $catalog, [[
            'path' => 'providers.0.identification',
            'multiple' => false,
        ]]));
        self::assertSame('', $validate($service, $submitted, $catalog, [], [
            'providers' => ['providers' => [[
                'identification' => ['name' => 'identification.pdf', 'document_uuid' => 'document-uuid'],
            ]]],
        ]));
    }

    public function testDraftButtonsUseStepValidationAndPostTheCurrentStep(): void
    {
        $template = file_get_contents(PLUGIN_APA_AGADEV_PATH . 'templates/agreement-form.php');
        $javascript = file_get_contents(PLUGIN_APA_AGADEV_PATH . 'assets/apa-agadev.js');
        $stylesheet = file_get_contents(PLUGIN_APA_AGADEV_PATH . 'assets/apa-agadev.css');

        self::assertIsString($template);
        self::assertIsString($javascript);
        self::assertIsString($stylesheet);
        // Native form validation would also inspect required controls in hidden
        // future steps; the JavaScript and PHP validators intentionally scope it.
        self::assertSame(2, substr_count($template, 'formnovalidate data-apa-save-draft'));
        self::assertStringContainsString('name="apa_agadev_current_step"', $template);
        self::assertStringContainsString('data-apa-required-group', $template);
        self::assertStringContainsString('dataset.apaFieldError', $javascript);
        self::assertStringContainsString("setAttribute('aria-invalid', 'true')", $javascript);
        self::assertStringContainsString('.acl_shortcode_apa_field_error', $stylesheet);
    }

    public function testProviderTypeKeepsTheTechnicalValueSubmittedByTheForm(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $normalize = Closure::bind(
            static fn (ShortcodeService $target, array $submitted, array $catalog, string $status): array =>
                $target->normalizeAgreement($submitted, $catalog, $status),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'providers' => ['subsections' => [
                'provider_identification' => ['fields' => [
                    'providers' => [
                        'type' => 'repeater',
                        'fields' => [
                            'type' => ['type' => 'select'],
                            'identification' => ['type' => 'file'],
                            'name' => ['type' => 'text'],
                            'address' => ['type' => 'text'],
                            'contact_person' => ['type' => 'text'],
                            'email' => ['type' => 'email'],
                            'phone' => ['type' => 'text'],
                        ],
                    ],
                ]],
            ]],
        ]];

        $payload = $normalize($service, [
            'providers' => [
                'providers' => [[
                    'type' => 'community_association',
                    'name' => 'Association locale',
                    'address' => 'Libreville, Gabon',
                    'contact_person' => 'Arielle M.',
                    'email' => 'arielle@example.test',
                    'phone' => '+241 07 00 00 00',
                ]],
            ],
        ], $catalog, 'pending');

        self::assertSame([
            'type' => 'community_association',
            'identification' => null,
            'name' => 'Association locale',
            'address' => 'Libreville, Gabon',
            'contact_person' => 'Arielle M.',
            'email' => 'arielle@example.test',
            'phone' => '+241 07 00 00 00',
        ], $payload['providers'][0]);
        self::assertArrayNotHasKey('providers', $payload['providers'][0]);
    }

    public function testProviderDocumentPathMatchesMaivouRootListContract(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $canonicalize = Closure::bind(
            static fn (ShortcodeService $target, string $path): string => $target->canonicalDocumentPath($path),
            null,
            ShortcodeService::class
        );

        self::assertSame(
            'providers.0.identification',
            $canonicalize($service, 'providers.providers.0.identification')
        );
        self::assertSame(
            'project.project_summary_attachment',
            $canonicalize($service, 'project.project_summary_attachment')
        );
    }

    public function testCompleteProviderPayloadIsHandedToMaivouWithoutAFormWrapper(): void
    {
        $providers = [[
            'type' => 'individual',
            'name' => 'Fournisseur test',
            'address' => 'Port-Gentil, Gabon',
            'contact_person' => 'Jean M.',
            'email' => 'jean@example.test',
            'phone' => '+241 06 00 00 00',
        ]];

        (new MaivouDataService())->createAgreement([
            'providers' => $providers,
            'status' => 'pending',
        ]);

        self::assertSame('/agreements', $GLOBALS['apa_test_api_arguments']['endpoint']);
        self::assertSame('POST', $GLOBALS['apa_test_api_arguments']['method']);
        self::assertSame($providers, $GLOBALS['apa_test_api_arguments']['body']['providers']);
        self::assertArrayNotHasKey('providers', $GLOBALS['apa_test_api_arguments']['body']['providers'][0]);
    }

    public function testMultipartProviderSubmissionUsesTheNativePayloadAndDocumentPath(): void
    {
        $providers = [
            [
                'type' => 'individual',
                'name' => 'Premier fournisseur',
                'email' => 'premier@example.test',
            ],
            [
                'type' => 'enterprise',
                'name' => 'Deuxième fournisseur',
                'email' => 'deuxieme@example.test',
            ],
        ];
        $files = [
            'documents[0]' => [
                'path' => '/tmp/provider-identification.pdf',
                'filename' => 'identification.pdf',
                'mime' => 'application/pdf',
            ],
        ];
        $manifest = [[
            'path' => 'providers.1.identification',
            'multiple' => false,
        ]];

        (new MaivouDataService())->createAgreement([
            'providers' => $providers,
            'status' => 'pending',
        ], $files, $manifest);

        $arguments = $GLOBALS['apa_test_api_arguments'];
        $payload = json_decode((string) $arguments['fields']['payload'], true);
        $submittedManifest = json_decode((string) $arguments['fields']['document_manifest'], true);

        self::assertSame('/agreements', $arguments['endpoint']);
        self::assertSame('POST', $arguments['method']);
        self::assertSame($providers, $payload['providers']);
        self::assertSame($manifest, $submittedManifest);
        self::assertSame($files, $arguments['files']);
    }

    public function testGeneticResourceTechnicalIdentifiersRemainCanonicalDuringSubmission(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $normalize = Closure::bind(
            static fn (ShortcodeService $target, array $submitted, array $catalog, string $status): array =>
                $target->normalizeAgreement($submitted, $catalog, $status),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'genetic_resources' => ['subsections' => [
                'resource_identification' => ['fields' => [
                    'resources' => ['type' => 'repeater', 'fields' => [
                        'product' => ['type' => 'select'],
                        'quantity' => ['type' => 'number'],
                    ]],
                ]],
                'collection_areas' => ['fields' => [
                    'collection_area_entries' => ['type' => 'repeater', 'fields' => [
                        'sample_reference' => ['type' => 'select'],
                        'origin' => ['type' => 'select'],
                    ]],
                ]],
            ]],
        ]];

        $payload = $normalize($service, [
            'genetic_resources' => [
                'resources' => [[
                    'product' => 'product-uuid',
                    'quantity' => '20000',
                ]],
                'collection_area_entries' => [[
                    'sample_reference' => 'product-uuid',
                    'origin' => 'zone-uuid',
                ]],
            ],
        ], $catalog, 'draft');

        self::assertSame('product-uuid', $payload['genetic_resources']['resources'][0]['product']);
        self::assertSame('product-uuid', $payload['genetic_resources']['collection_area_entries'][0]['sample_reference']);
        self::assertSame('zone-uuid', $payload['genetic_resources']['collection_area_entries'][0]['origin']);
    }

    public function testProviderDraftRowsAreRestoredToTheFormShape(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $restore = Closure::bind(
            static fn (ShortcodeService $target, array $agreement, array $catalog): array =>
                $target->agreementFormValues($agreement, $catalog),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'providers' => ['subsections' => [
                'provider_identification' => ['fields' => [
                    'providers' => [
                        'type' => 'repeater',
                        'fields' => [
                            'type' => ['type' => 'select'],
                            'name' => ['type' => 'text'],
                        ],
                    ],
                ]],
            ]],
        ]];
        $providerRows = [[
            'type' => 'community_association',
            'name' => 'Association locale',
            'identification' => [
                'name' => 'identification.pdf',
                'document_uuid' => '740b2a78-29c3-4382-8f54-aa15d02e325c',
            ],
        ]];

        self::assertSame([
            'providers' => ['providers' => $providerRows],
        ], $restore($service, ['providers' => $providerRows], $catalog));
    }

    public function testAgreementFormDisplaysTheStoredDocumentName(): void
    {
        $template = file_get_contents(PLUGIN_APA_AGADEV_PATH . 'templates/agreement-form.php');

        self::assertIsString($template);
        self::assertStringContainsString('Document déjà joint', $template);
        self::assertStringContainsString('$existing_document_names', $template);
        self::assertStringContainsString('Il sera conservé si vous enregistrez de nouveau ce brouillon.', $template);
    }

    public function testProviderDraftUpdatePreservesItsIdentificationDocument(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $preserve = Closure::bind(
            static fn (ShortcodeService $target, array $payload, array $existing, array $catalog): array =>
                $target->preserveDocumentValues($payload, $existing, $catalog),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'providers' => ['subsections' => [
                'provider_identification' => ['fields' => [
                    'providers' => [
                        'type' => 'repeater',
                        'fields' => [
                            'type' => ['type' => 'select'],
                            'name' => ['type' => 'text'],
                            'identification' => ['type' => 'file'],
                        ],
                    ],
                ]],
            ]],
        ]];
        $payload = ['providers' => [[
            'type' => 'individual',
            'name' => 'Fournisseur',
        ]]];
        $document = ['id' => 42, 'name' => 'identification.pdf'];
        $existing = ['providers' => [[
            'type' => 'individual',
            'name' => 'Fournisseur',
            'identification' => $document,
        ]]];

        $result = $preserve($service, $payload, $existing, $catalog);

        self::assertSame($document, $result['providers'][0]['identification']);
    }

    public function testWordPressSubmissionCanBuildAnIncompleteDraft(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $normalize = Closure::bind(
            static fn (ShortcodeService $target, array $submitted, array $catalog, string $status, bool $includeEmpty = false): array =>
                $target->normalizeAgreement($submitted, $catalog, $status, $includeEmpty),
            null,
            ShortcodeService::class
        );

        /** @var array<string, mixed> $payload */
        $payload = $normalize($service, [], ['sections' => []], 'draft');

        self::assertSame(['status' => 'draft'], $payload);
    }

    public function testDraftUpdateCanExplicitlyClearAStoredField(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $normalize = Closure::bind(
            static fn (ShortcodeService $target, array $submitted, array $catalog, string $status, bool $includeEmpty): array =>
                $target->normalizeAgreement($submitted, $catalog, $status, $includeEmpty),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'project' => ['fields' => [
                'project_title' => ['type' => 'text'],
                'budget' => ['type' => 'number'],
            ]],
        ]];

        /** @var array<string, mixed> $payload */
        $payload = $normalize(
            $service,
            ['project' => ['project_title' => '', 'budget' => '']],
            $catalog,
            'draft',
            true
        );

        self::assertArrayHasKey('project_title', $payload['project']);
        self::assertNull($payload['project']['project_title']);
        self::assertNull($payload['project']['budget']);
    }

    public function testSubmissionIntentRejectsAnUnknownBrowserValue(): void
    {
        $_POST['apa_agadev_submission_intent'] = 'accepted';
        $service = new ShortcodeService(new MaivouDataService());
        $submissionIntent = Closure::bind(
            static fn (ShortcodeService $target): string => $target->submissionIntent(),
            null,
            ShortcodeService::class
        );

        self::assertSame('', $submissionIntent($service));
    }

    public function testDraftUpdatePreservesAnExistingDocumentReference(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $preserve = Closure::bind(
            static fn (ShortcodeService $target, array $payload, array $existing, array $catalog): array =>
                $target->preserveDocumentValues($payload, $existing, $catalog),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'additional_documents' => ['fields' => [
                'title' => ['type' => 'text'],
                'attached_document' => ['type' => 'file'],
            ]],
        ]];

        /** @var array<string, mixed> $payload */
        $payload = $preserve(
            $service,
            ['additional_documents' => ['title' => 'Autorisation']],
            ['additional_documents' => [
                'title' => 'Ancien titre',
                'attached_document' => 'document-uuid',
            ]],
            $catalog
        );

        self::assertSame('Autorisation', $payload['additional_documents']['title']);
        self::assertSame('document-uuid', $payload['additional_documents']['attached_document']);
    }

    public function testOptionalEmptyDocumentsAreNullInJsonMultipartAndUpdates(): void
    {
        $data = new MaivouDataService();
        $service = new ShortcodeService($data);
        $normalize = Closure::bind(
            static fn (array $submitted, array $catalog): array =>
                $service->normalizeAgreement($submitted, $catalog, 'draft'),
            null,
            ShortcodeService::class
        );
        $preserve = Closure::bind(
            static fn (array $payload, array $existing, array $catalog): array =>
                $service->preserveDocumentValues($payload, $existing, $catalog),
            null,
            ShortcodeService::class
        );
        $catalog = ['sections' => [
            'project' => ['fields' => [
                'attachment' => ['type' => 'file', 'required' => false],
                'documents' => ['type' => 'dropzone'],
                'required_document' => ['type' => 'file', 'required' => true],
            ]],
            'providers' => ['subsections' => ['identity' => ['fields' => [
                'providers' => ['type' => 'repeater', 'fields' => [
                    'name' => ['type' => 'text'],
                    'identification' => ['type' => 'file'],
                ]],
            ]]]],
        ]];
        $payload = $normalize(['providers' => ['providers' => [['name' => 'Fournisseur']]]], $catalog);
        self::assertSame(['attachment' => null, 'documents' => null], $payload['project']);
        self::assertSame(['name' => 'Fournisseur', 'identification' => null], $payload['providers'][0]);
        $data->createAgreement($payload);
        self::assertSame($payload, $GLOBALS['apa_test_api_arguments']['body']);

        $files = ['documents[0]' => ['path' => '/tmp/test.pdf', 'filename' => 'test.pdf', 'mime' => 'application/pdf']];
        $manifest = [['path' => 'project.required_document', 'multiple' => false]];
        $data->createAgreement($payload, $files, $manifest);
        self::assertSame($payload, json_decode($GLOBALS['apa_test_api_arguments']['fields']['payload'], true));
        self::assertSame($files, $GLOBALS['apa_test_api_arguments']['files']);
        self::assertSame($manifest, json_decode($GLOBALS['apa_test_api_arguments']['fields']['document_manifest'], true));

        $updated = $preserve($payload, [
            'project' => ['attachment' => ['uuid' => 'existing-document']],
            'providers' => [['name' => 'Fournisseur', 'identification' => 'existing-provider-document']],
        ], $catalog);
        $data->updateAgreement(31, $updated);
        $sent = $GLOBALS['apa_test_api_arguments']['body'];
        self::assertSame(['uuid' => 'existing-document'], $sent['project']['attachment']);
        self::assertArrayHasKey('documents', $sent['project']);
        self::assertNull($sent['project']['documents']);
        self::assertSame('existing-provider-document', $sent['providers'][0]['identification']);
    }

    public function testMaivouDraftUpdateUsesTheAuthenticatedPutEndpoint(): void
    {
        (new MaivouDataService())->updateAgreement(42, ['status' => 'pending']);

        self::assertSame('/agreements/42', $GLOBALS['apa_test_api_arguments']['endpoint']);
        self::assertSame('PUT', $GLOBALS['apa_test_api_arguments']['method']);
        self::assertSame(['status' => 'pending'], $GLOBALS['apa_test_api_arguments']['body']);
        self::assertSame('user', $GLOBALS['apa_test_api_arguments']['auth']);
    }

    public function testAgreementActionsUseAccessibleIconsAndTooltips(): void
    {
        $template = file_get_contents(PLUGIN_APA_AGADEV_PATH . 'templates/agreements.php');

        self::assertIsString($template);
        self::assertStringContainsString('acl_shortcode_agreements_actions_heading', $template);
        self::assertStringContainsString("esc_html_e('Actions'", $template);
        self::assertStringContainsString("esc_attr_e('Modifier le brouillon'", $template);
        self::assertStringContainsString("esc_attr_e('Consulter la demande'", $template);
        self::assertStringContainsString("esc_attr_e('Télécharger le PDF'", $template);
        self::assertSame(3, substr_count($template, 'data-tooltip='));
        self::assertSame(3, substr_count($template, 'aria-hidden="true" focusable="false"'));
    }

    public function testAgreementModalSeparatesCreationAndEditingContexts(): void
    {
        $template = file_get_contents(PLUGIN_APA_AGADEV_PATH . 'templates/agreements.php');
        $script = file_get_contents(PLUGIN_APA_AGADEV_PATH . 'assets/apa-agadev.js');

        self::assertIsString($template);
        self::assertIsString($script);
        self::assertStringContainsString('data-apa-agreement-create-url=', $template);
        self::assertStringContainsString('data-apa-agreement-mode=', $template);
        self::assertGreaterThanOrEqual(2, substr_count($template, 'data-apa-agreement-modal-close'));
        self::assertStringContainsString("var wasEditing = 'edit'", $script);
        self::assertStringContainsString('window.location.replace(successReturnUrl)', $script);
        self::assertStringContainsString("'Escape' !== event.key", $script);
        self::assertStringContainsString('closeAgreementModal(modal)', $script);
    }
}
