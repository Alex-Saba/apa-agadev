<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PluginApaAgadev\Service\MaivouDataService;
use PluginApaAgadev\Service\ShortcodeService;

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
            'provider_identification' => ['fields' => [
                'providers' => [
                    'type' => 'repeater',
                    'fields' => [
                        'type' => ['type' => 'select'],
                        'name' => ['type' => 'text'],
                    ],
                ],
            ]],
        ]];

        $payload = $normalize($service, [
            'provider_identification' => [
                'providers' => [[
                    'type' => 'community_association',
                    'name' => 'Association locale',
                ]],
            ],
        ], $catalog, 'pending');

        self::assertSame(
            'community_association',
            $payload['provider_identification']['providers'][0]['type']
        );
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
