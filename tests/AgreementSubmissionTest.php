<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PluginApaAgadev\Service\MaivouDataService;
use PluginApaAgadev\Service\ShortcodeService;

final class AgreementSubmissionTest extends TestCase
{
    public function testWordPressSubmissionForcesPendingWithoutChangingFormData(): void
    {
        $service = new ShortcodeService(new MaivouDataService());
        $normalize = Closure::bind(
            static fn (ShortcodeService $target, array $submitted, array $catalog): array =>
                $target->normalizeAgreement($submitted, $catalog),
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
        $payload = $normalize($service, $submitted, $catalog);

        self::assertSame('pending', $payload['status']);
        self::assertSame([
            ['product' => 'product-uuid', 'quantity' => 15],
        ], $payload['genetic_resources']['resources']);
    }
}
