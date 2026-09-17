<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PluginApaAgadev\Service\AgreementFormOptionNormalizer;

if (! function_exists('esc_attr_e')) {
    function esc_attr_e(string $text, string $domain = ''): void
    {
        echo esc_attr($text);
    }
}

if (! function_exists('esc_textarea')) {
    function esc_textarea(string $text): string
    {
        return esc_html($text);
    }
}

if (! function_exists('checked')) {
    function checked($checked, $current = true): void
    {
        if ($checked === $current) {
            echo ' checked="checked"';
        }
    }
}

if (! function_exists('wp_nonce_field')) {
    function wp_nonce_field(string $action, string $name): void
    {
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($action) . '">';
    }
}

final class AgreementFormOptionNormalizerTest extends TestCase
{
    public function testAssociativeOptionsKeepTheirTechnicalKeys(): void
    {
        self::assertSame([
            'community_association' => 'Association communautaire',
            'enterprise' => 'Entreprise',
        ], AgreementFormOptionNormalizer::normalize([
            'community_association' => 'Association communautaire',
            'enterprise' => 'Entreprise',
        ]));
    }

    public function testStructuredListsUseTechnicalValuesInsteadOfNumericIndexes(): void
    {
        $options = AgreementFormOptionNormalizer::normalize([
            ['value' => 'community_association', 'label' => 'Association communautaire'],
            (object) ['id' => 'enterprise', 'name' => 'Entreprise'],
            ['code' => 'research_organization', 'title' => 'Organisme de recherche'],
            ['uuid' => 'individual', 'label' => 'Individu'],
        ]);

        self::assertSame([
            'community_association' => 'Association communautaire',
            'enterprise' => 'Entreprise',
            'research_organization' => 'Organisme de recherche',
            'individual' => 'Individu',
        ], $options);
        self::assertArrayNotHasKey(0, $options);
        self::assertArrayNotHasKey('0', $options);
    }

    public function testValueAndLabelPrioritiesAreDeterministic(): void
    {
        self::assertSame([
            'preferred-value' => 'Preferred label',
        ], AgreementFormOptionNormalizer::normalize([[
            'value' => 'preferred-value',
            'code' => 'secondary-code',
            'uuid' => 'secondary-uuid',
            'label' => 'Preferred label',
            'name' => 'Secondary name',
        ]]));
    }

    public function testScalarOptionsAndInvalidEntriesAreHandledSafely(): void
    {
        self::assertSame([
            'individual' => 'individual',
        ], AgreementFormOptionNormalizer::normalize([
            'individual',
            ['value' => '', 'label' => ''],
            new stdClass(),
        ]));
    }

    public function testProductEndpointUsesUuidInsteadOfCode(): void
    {
        self::assertSame([
            '00000000-0000-4000-8000-000000000301' => 'Résine d’Okoumé',
        ], AgreementFormOptionNormalizer::normalize([[
            'id' => 12,
            'uuid' => '00000000-0000-4000-8000-000000000301',
            'code' => 'RES-001',
            'name' => 'Résine d’Okoumé',
        ]], '/api/products'));
    }

    public function testZoneEndpointUsesUuidAndGeographicLabel(): void
    {
        self::assertSame([
            '109a4b2b-9cd4-44ff-a7a9-d79a9e8d29bd' => 'Estuaire — Komo-Mondah — Ntoum',
        ], AgreementFormOptionNormalizer::normalize([[
            'id' => 42,
            'uuid' => '109a4b2b-9cd4-44ff-a7a9-d79a9e8d29bd',
            'province_name' => 'Estuaire',
            'department_name' => 'Komo-Mondah',
            'department_capital_name' => 'Ntoum',
        ]], '/api/zones'));
    }

    public function testLegacyIdentifiersResolveToCanonicalEndpointValues(): void
    {
        $product = [[
            'id' => 12,
            'uuid' => 'product-uuid',
            'code' => 'RES-001',
            'name' => 'Résine d’Okoumé',
        ]];
        $zone = [[
            'id' => 42,
            'uuid' => 'zone-uuid',
            'province_name' => 'Estuaire',
            'department_name' => 'Komo-Mondah',
        ]];

        self::assertSame(
            ['product-uuid'],
            AgreementFormOptionNormalizer::normalizeSelected('RES-001', $product, '/api/products')
        );
        self::assertSame(
            ['zone-uuid'],
            AgreementFormOptionNormalizer::normalizeSelected('42', $zone, '/api/zones')
        );
        self::assertSame(
            ['zone-uuid'],
            AgreementFormOptionNormalizer::normalizeSelected('zone-uuid', $zone, '/api/zones')
        );
    }

    public function testLegacyDraftValuesRenderAsSelectedCanonicalOptions(): void
    {
        $catalog = ['sections' => [
            'genetic_resources' => [
                'title' => 'Identification des ressources génétiques',
                'fields' => [
                    'resources' => [
                        'type' => 'repeater',
                        'label' => 'Ressources génétiques',
                        'fields' => [
                            'product' => [
                                'type' => 'select',
                                'label' => 'Produit',
                                'optionsEndpoint' => '/api/products',
                            ],
                        ],
                    ],
                    'collection_area_entries' => [
                        'type' => 'repeater',
                        'label' => 'Zones prévues de collecte',
                        'fields' => [
                            'origin' => [
                                'type' => 'select',
                                'label' => 'Origine',
                                'optionsEndpoint' => '/api/zones',
                            ],
                        ],
                    ],
                ],
            ],
        ]];
        $remote_options = [
            '/api/products' => [[
                'id' => 12,
                'uuid' => 'product-uuid',
                'code' => 'RES-001',
                'name' => 'Résine d’Okoumé',
            ]],
            '/api/zones' => [[
                'id' => 42,
                'uuid' => 'zone-uuid',
                'province_name' => 'Estuaire',
                'department_name' => 'Komo-Mondah',
            ]],
        ];
        $submitted = ['genetic_resources' => [
            'resources' => [['product' => 'RES-001']],
            'collection_area_entries' => [['origin' => '42']],
        ]];
        $submission = null;
        $submission_intent = '';
        $editing_agreement_id = 42;
        $layout = 'modal';

        ob_start();
        require PLUGIN_APA_AGADEV_PATH . 'templates/agreement-form.php';
        $html = (string) ob_get_clean();

        self::assertMatchesRegularExpression('/value="product-uuid"[^>]* selected/', $html);
        self::assertStringContainsString('value="zone-uuid" selected', $html);
        self::assertStringNotContainsString('value="RES-001"', $html);
        self::assertDoesNotMatchRegularExpression('/<option[^>]+value="42"/', $html);
    }

    public function testProductUnitsRenderForEachDraftRowAndEmptyTemplate(): void
    {
        $catalog = ['sections' => ['genetic_resources' => ['fields' => [
            'resources' => ['type' => 'repeater', 'fields' => [
                'product' => ['type' => 'select', 'optionsEndpoint' => '/api/products'],
                'quantity' => ['type' => 'number', 'label' => 'Quantité visée'],
            ]],
        ]]]];
        $remote_options = ['/api/products' => [
            ['uuid' => 'product-kg', 'code' => 'OLD-KG', 'name' => 'Résine', 'assigned_packaging_unit' => 'kg'],
            ['uuid' => 'product-l', 'name' => 'Huile', 'assigned_packaging_unit' => 'l'],
            ['uuid' => 'product-piece', 'name' => 'Graine', 'assigned_packaging_unit' => 'piece'],
            ['uuid' => 'product-null', 'name' => 'Sans unité', 'assigned_packaging_unit' => null],
            ['uuid' => 'product-missing', 'name' => 'Ancien produit'],
        ]];
        $submitted = ['genetic_resources' => ['resources' => [
            ['product' => 'OLD-KG', 'quantity' => 12],
            ['product' => 'product-l', 'quantity' => 3],
            ['product' => 'product-piece', 'quantity' => 2],
            ['product' => 'product-null', 'quantity' => 1],
            ['product' => 'product-missing', 'quantity' => 4],
        ]]];
        $submission = null;
        $submission_intent = '';
        $editing_agreement_id = 42;
        $layout = 'modal';
        ob_start();
        require PLUGIN_APA_AGADEV_PATH . 'templates/agreement-form.php';
        $html = (string) ob_get_clean();

        preg_match_all('/<span data-apa-product-unit[^>]*>(.*?)<\/span>/s', $html, $matches);
        self::assertSame(['(kg)', '(l)', '(pièce)', '(Unité non renseignée)', '(Unité non renseignée)', ''], $matches[1]);
        self::assertStringContainsString('value="product-kg" data-apa-unit="kg" selected', $html);
        self::assertStringContainsString('data-apa-product-select', $html);
        self::assertStringContainsString('value="12"', $html);
        self::assertStringNotContainsString('name="assigned_packaging_unit"', $html);
    }

    public function testProviderTypeRendersItsTechnicalValueInTheFormHtml(): void
    {
        $catalog = ['sections' => [
            'provider_identification' => [
                'title' => 'Identification du fournisseur',
                'fields' => [
                    'providers' => [
                        'type' => 'repeater',
                        'label' => 'Fournisseurs',
                        'fields' => [
                            'type' => [
                                'type' => 'select',
                                'label' => 'Type',
                                'options' => [
                                    ['value' => 'community_association', 'label' => 'Association communautaire'],
                                    ['value' => 'enterprise', 'label' => 'Entreprise'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]];
        $remote_options = [];
        $submitted = [];
        $submission = null;
        $submission_intent = '';
        $editing_agreement_id = 0;
        $layout = 'modal';

        ob_start();
        require PLUGIN_APA_AGADEV_PATH . 'templates/agreement-form.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('value="community_association"', $html);
        self::assertStringContainsString('>Association communautaire</option>', $html);
        self::assertStringNotContainsString('<option class="acl_shortcode_option" value="0"', $html);
    }
}
