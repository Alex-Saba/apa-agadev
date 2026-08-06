<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PluginApaAgadev\Service\AgreementPresentationService;

final class AgreementPresentationServiceTest extends TestCase
{
    public function testItUsesOnlyDisplayValuesAndPreservesPresentationOrder(): void
    {
        $detail = (new AgreementPresentationService())->present($this->agreementFixture());

        self::assertSame('Identification des ressources génétiques', $detail['sections'][0]['label']);
        self::assertSame('Résine d’Okoumé', $detail['sections'][0]['groups'][0]['entries'][0]['fields'][0]['display_value']);
        self::assertSame(
            ['Impact local', 'Impact temporaire'],
            $detail['sections'][0]['groups'][1]['entries'][0]['fields'][0]['display_value']
        );

        $serialized = json_encode($detail, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('00000000-0000-4000-8000-000000000301', $serialized);
        self::assertStringNotContainsString('"local"', $serialized);
        self::assertStringNotContainsString('"temporary"', $serialized);
    }

    public function testItPreservesRepeaterEntries(): void
    {
        $detail = (new AgreementPresentationService())->present($this->agreementFixture());
        $entries = $detail['sections'][0]['groups'][0]['entries'];

        self::assertCount(2, $entries);
        self::assertSame('Résine d’Okoumé', $entries[0]['fields'][0]['display_value']);
        self::assertSame('Miel forestier', $entries[1]['fields'][0]['display_value']);
    }

    public function testItAcceptsAnEmptySectionsCollection(): void
    {
        $agreement = $this->agreementFixture();
        $agreement['presentation']['sections'] = [];

        $detail = (new AgreementPresentationService())->present($agreement);

        self::assertSame([], $detail['sections']);
    }

    public function testItRejectsMissingPresentationWithoutFallingBackToRawPayload(): void
    {
        $agreement = $this->agreementFixture();
        unset($agreement['presentation']);

        $this->expectException(UnexpectedValueException::class);
        (new AgreementPresentationService())->present($agreement);
    }

    public function testItRejectsAFieldWithoutDisplayValue(): void
    {
        $agreement = $this->agreementFixture();
        unset($agreement['presentation']['sections'][0]['groups'][0]['entries'][0]['fields'][0]['display_value']);

        $this->expectException(UnexpectedValueException::class);
        (new AgreementPresentationService())->present($agreement);
    }

    public function testHtmlRendersDisplayValuesWithoutRawIdentifiers(): void
    {
        $agreement_detail = (new AgreementPresentationService())->present($this->agreementFixture());
        $back_url = 'https://example.test/home-user/?view=agrements';
        $download_url = 'https://example.test/?apa_agadev_download=1';

        ob_start();
        require dirname(__DIR__) . '/templates/agreement-detail.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Résine d’Okoumé', $html);
        self::assertStringContainsString('Impact local', $html);
        self::assertStringContainsString('data-apa-agreement-detail', $html);
        self::assertStringContainsString('data-apa-agreement-long="0"', $html);
        self::assertStringContainsString('data-apa-agreement-section open', $html);
        self::assertStringContainsString('aria-expanded="true"', $html);
        self::assertStringNotContainsString('00000000-0000-4000-8000-000000000301', $html);
        self::assertStringNotContainsString('>local<', $html);
        self::assertStringNotContainsString('>temporary<', $html);
    }

    public function testLongHtmlDetailOpensOnlyItsFirstSection(): void
    {
        $agreement_detail = (new AgreementPresentationService())->present($this->agreementFixture());
        $base_section = $agreement_detail['sections'][0];
        $agreement_detail['sections'] = [];

        for ($index = 1; $index <= 4; $index++) {
            $section = $base_section;
            $section['key'] = 'section_' . $index;
            $section['label'] = 'Section ' . $index;
            $agreement_detail['sections'][] = $section;
        }

        $back_url = 'https://example.test/home-user/?view=agrements';
        $download_url = 'https://example.test/?apa_agadev_download=1';

        ob_start();
        require dirname(__DIR__) . '/templates/agreement-detail.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('data-apa-agreement-long="1"', $html);
        self::assertSame(4, substr_count($html, 'class="acl_shortcode_agreement_section"'));
        self::assertSame(1, substr_count($html, 'data-apa-agreement-section open>'));
        self::assertStringContainsString('Tout déplier', $html);
        self::assertStringContainsString('Tout replier', $html);
        self::assertStringContainsString('Sections de la demande', $html);
        self::assertStringContainsString('<aside class="acl_shortcode_agreement_navigation', $html);
        self::assertStringContainsString('class="acl_shortcode_agreement_content', $html);
    }

    /** @return array<string, mixed> */
    private function agreementFixture(): array
    {
        return [
            'id' => 42,
            'uuid' => 'aaaaaaaa-aaaa-4000-8000-aaaaaaaaaaaa',
            'code' => 'APA-2026-0042',
            'status' => 'pending',
            'created_at' => '2026-08-06T10:00:00Z',
            'user' => ['firstname' => 'Alex', 'lastname' => 'Saba'],
            'genetic_resources' => ['impact_values' => ['local', 'temporary']],
            'presentation' => [
                'sections' => [[
                    'key' => 'genetic_resources',
                    'label' => 'Identification des ressources génétiques',
                    'groups' => [
                        [
                            'key' => 'resources',
                            'label' => 'Ressources génétiques',
                            'entries' => [
                                ['fields' => [[
                                    'key' => 'product',
                                    'label' => 'Produit',
                                    'value' => '00000000-0000-4000-8000-000000000301',
                                    'display_value' => 'Résine d’Okoumé',
                                ]]],
                                ['fields' => [[
                                    'key' => 'product',
                                    'label' => 'Produit',
                                    'value' => '00000000-0000-4000-8000-000000000302',
                                    'display_value' => 'Miel forestier',
                                ]]],
                            ],
                        ],
                        [
                            'key' => 'impact_values',
                            'label' => 'Évaluation de l’impact',
                            'entries' => [['fields' => [[
                                'key' => 'impact_values',
                                'label' => 'Éléments d’impact',
                                'value' => ['local', 'temporary'],
                                'display_value' => ['Impact local', 'Impact temporaire'],
                            ]]]],
                        ],
                    ],
                ]],
            ],
        ];
    }
}
