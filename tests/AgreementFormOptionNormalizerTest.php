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
