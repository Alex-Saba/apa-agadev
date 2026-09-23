<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PluginApaAgadev\Service\AgreementPdfService;
use PluginApaAgadev\Service\AgreementPresentationService;
use PluginApaAgadev\Service\MaivouDataService;

final class AgreementPdfServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        unset(
            $GLOBALS['apa_test_logged_in'],
            $GLOBALS['apa_test_api_arguments'],
            $GLOBALS['apa_test_api_response']
        );
    }

    public function testDownloadUrlIsBoundToTheAgreementNonce(): void
    {
        $url = AgreementPdfService::downloadUrl(42);

        self::assertStringContainsString('agreement_id=42', $url);
        self::assertStringContainsString('_wpnonce=apa_agadev_download_agreement_pdf_42', $url);
    }

    public function testItGeneratesAPrintableViewFromDisplayValues(): void
    {
        $agreement = [
            'id' => 42,
            'code' => 'APA-2026-0042',
            'status' => 'accepted',
            'created_at' => '2026-08-06T10:00:00Z',
            'starts_at' => '2026-08-07',
            'ends_at' => '2027-08-07',
            'user' => ['firstname' => 'Alex', 'lastname' => 'Saba'],
            'presentation' => ['sections' => [
                [
                    'key' => 'genetic_resources',
                    'label' => 'Identification des ressources génétiques',
                    'groups' => [
                        [
                            'key' => 'resources',
                            'label' => 'Ressources génétiques',
                            'entries' => [
                                ['fields' => [
                                    [
                                        'key' => 'product',
                                        'label' => 'Produit',
                                        'value' => '00000000-0000-4000-8000-000000000301',
                                        'display_value' => 'Résine d’Okoumé',
                                    ],
                                    ['key' => 'quantity', 'label' => 'Quantité visée', 'value' => 12, 'display_value' => 12],
                                ]],
                                ['fields' => [
                                    ['key' => 'product', 'label' => 'Produit', 'value' => 'technical-product-2', 'display_value' => 'Miel forestier'],
                                    ['key' => 'quantity', 'label' => 'Quantité visée', 'value' => 8, 'display_value' => 8],
                                ]],
                            ],
                        ],
                        [
                            'key' => 'collection_area_entries',
                            'label' => 'Zones prévues de collecte',
                            'entries' => [['fields' => [
                                ['key' => 'sample_reference', 'label' => 'Référence échantillon', 'value' => 'RES-001', 'display_value' => 'Résine d’Okoumé'],
                                ['key' => 'origin', 'label' => 'Origine', 'value' => 'technical-zone', 'display_value' => 'Estuaire — Komo — Libreville'],
                            ]]],
                        ],
                    ],
                ],
                [
                    'key' => 'project',
                    'label' => 'Projet',
                    'groups' => [[
                        'key' => 'project_identity',
                        'label' => 'Présentation du projet',
                        'entries' => [['fields' => [
                            ['key' => 'project_title', 'label' => 'Titre du projet', 'value' => 'raw-title', 'display_value' => 'Valorisation durable des ressources forestières'],
                            ['key' => 'project_summary', 'label' => 'Résumé', 'value' => 'raw-summary', 'display_value' => 'Ce projet étudie des pratiques de collecte responsables et leur contribution au développement local tout en préservant les ressources naturelles.'],
                        ]]],
                    ]],
                ],
            ]],
        ];
        $detail = (new AgreementPresentationService())->present($agreement);
        $service = new AgreementPdfService(new MaivouDataService());
        $html = $service->renderPrintView($detail);

        // Allows explicit visual QA without leaving artifacts during normal tests.
        $artifactPath = getenv('APA_PRINT_ARTIFACT');
        if (is_string($artifactPath) && $artifactPath !== '') {
            file_put_contents($artifactPath, $html);
        }

        self::assertStringContainsString('<!doctype html>', $html);
        self::assertStringContainsString('Résine d’Okoumé', $html);
        self::assertStringContainsString('Estuaire — Komo — Libreville', $html);
        self::assertStringContainsString('window.print()', $html);
        self::assertStringContainsString('@media print', $html);
        self::assertStringContainsString('size: A4', $html);
        self::assertStringContainsString('.print-toolbar { display: none !important; }', $html);
        self::assertStringNotContainsString('00000000-0000-4000-8000-000000000301', $html);
    }

    public function testPdfTemplateEmbedsTheLocalAgadevLogo(): void
    {
        $service = new AgreementPdfService(new MaivouDataService());
        $detail = [
            'code' => 'APA-2026-0042',
            'status_label' => 'En attente',
            'holder' => 'Alex Saba',
            'sections' => [],
        ];

        // Call the private renderer in its own class scope without changing production visibility.
        $html = (function (array $agreementDetail): string {
            return $this->renderTemplate($agreementDetail);
        })->call($service, $detail);

        self::assertIsString($html);
        self::assertStringContainsString('data:image/svg+xml;base64,', $html);
        self::assertStringContainsString('alt="Logo AGADEV"', $html);
        self::assertStringContainsString('Agence Gabonaise pour le Développement de l’Économie Verte', $html);
        self::assertStringContainsString('République Gabonaise · Union · Travail · Justice', $html);
        self::assertStringContainsString('Document administratif', $html);
        self::assertStringContainsString('Formulaire de demande APA', $html);
        self::assertStringContainsString('Référence du dossier : APA-2026-0042', $html);
        self::assertStringContainsString('Informations du dossier', $html);
        self::assertStringContainsString('Type de demande', $html);
        self::assertStringContainsString('Demande APA', $html);
        self::assertStringContainsString('Les informations ci-dessus constituent la synthèse de la demande transmise.', $html);
        self::assertStringContainsString('Cadre réservé à l’administration', $html);
        self::assertStringContainsString('Recevabilité du dossier', $html);
        self::assertStringContainsString('Nom et qualité de l’autorité compétente', $html);
        self::assertStringContainsString('Authenticité du document', $html);
        self::assertStringNotContainsString('MAIVOU <span', $html);
    }

    public function testDownloadRejectsAnonymousUsersBeforeCallingMaivou(): void
    {
        $_GET = ['apa_agadev_download' => '1'];
        $GLOBALS['apa_test_logged_in'] = false;

        try {
            (new AgreementPdfService(new MaivouDataService()))->handleDownload();
            self::fail('The anonymous download should have been rejected.');
        } catch (ApaAgadevWpDieException $exception) {
            self::assertSame(401, $exception->response);
            self::assertArrayNotHasKey('apa_test_api_arguments', $GLOBALS);
        }
    }

    public function testDownloadRejectsAnInvalidNonceBeforeCallingMaivou(): void
    {
        $_GET = [
            'apa_agadev_download' => '1',
            'agreement_id' => '42',
            '_wpnonce' => 'invalid',
        ];
        $GLOBALS['apa_test_logged_in'] = true;

        try {
            (new AgreementPdfService(new MaivouDataService()))->handleDownload();
            self::fail('The invalid nonce should have been rejected.');
        } catch (ApaAgadevWpDieException $exception) {
            self::assertSame(403, $exception->response);
            self::assertArrayNotHasKey('apa_test_api_arguments', $GLOBALS);
        }
    }

    public function testPrintViewEscapesDisplayedContent(): void
    {
        $html = (new AgreementPdfService(new MaivouDataService()))->renderPrintView([
            'code' => '<script>alert(1)</script>',
            'holder' => '<img src=x onerror=alert(1)>',
            'sections' => [],
        ]);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testPrintRequestHonorsMaivouAccessDenial(): void
    {
        $_GET = [
            'apa_agadev_download' => '1',
            'agreement_id' => '42',
            '_wpnonce' => 'apa_agadev_download_agreement_pdf_42',
        ];
        $GLOBALS['apa_test_logged_in'] = true;
        $GLOBALS['apa_test_api_response'] = ['ok' => false, 'status' => 403, 'data' => null, 'error' => 'Accès refusé'];
        try {
            (new AgreementPdfService(new MaivouDataService()))->handleDownload();
            self::fail('Maivou access denial must prevent printing.');
        } catch (ApaAgadevWpDieException $exception) {
            self::assertSame(403, $exception->response);
            self::assertSame('/agreements/42', $GLOBALS['apa_test_api_arguments']['endpoint']);
            self::assertSame('user', $GLOBALS['apa_test_api_arguments']['auth']);
        }
    }

    public function testAgreementDetailDelegatesAuthorizationToTheBridgeWithUserAuth(): void
    {
        (new MaivouDataService())->getAgreement(42);

        self::assertSame('/agreements/42', $GLOBALS['apa_test_api_arguments']['endpoint']);
        self::assertSame('GET', $GLOBALS['apa_test_api_arguments']['method']);
        self::assertSame('user', $GLOBALS['apa_test_api_arguments']['auth']);
    }
}
