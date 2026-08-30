<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

use UnexpectedValueException;

/**
 * Validates Maivou's display-ready agreement representation.
 *
 * Raw answers are deliberately excluded from the returned view model so a
 * template cannot accidentally expose an UUID or another technical value.
 */
final class AgreementPresentationService
{
    /**
     * @param array<string, mixed> $agreement
     * @return array<string, mixed>
     */
    public function present(array $agreement): array
    {
        $presentation = $agreement['presentation'] ?? null;

        if (! is_array($presentation) || ! isset($presentation['sections']) || ! is_array($presentation['sections'])) {
            throw new UnexpectedValueException('The agreement presentation is missing or invalid.');
        }

        $sections = [];

        foreach ($presentation['sections'] as $section) {
            $normalized = $this->normalizeSection($section);

            if ($normalized !== null) {
                $sections[] = $normalized;
            }
        }

        $user = is_array($agreement['user'] ?? null) ? $agreement['user'] : [];

        return [
            'id' => (int) ($agreement['id'] ?? 0),
            'code' => trim((string) ($agreement['code'] ?? '')),
            'status' => strtolower(trim((string) ($agreement['status'] ?? ''))),
            'status_label' => $this->statusLabel((string) ($agreement['status'] ?? '')),
            'created_at' => $this->formatDate($agreement['created_at'] ?? null),
            'consented_at' => $this->formatDate($agreement['consented_at'] ?? null),
            'starts_at' => $this->formatDate($agreement['starts_at'] ?? null),
            'ends_at' => $this->formatDate($agreement['ends_at'] ?? null),
            'signature' => trim((string) ($agreement['signature'] ?? '')),
            'holder' => trim(implode(' ', array_filter([
                (string) ($user['firstname'] ?? ''),
                (string) ($user['lastname'] ?? ''),
            ]))),
            'documents' => $this->agreementDocuments($agreement),
            'sections' => $sections,
        ];
    }

    /**
     * Extracts only display-safe metadata from documents attached to the
     * agreement. Storage identifiers deliberately stay outside the view model.
     *
     * @param array<string, mixed> $agreement
     * @return list<array{name:string,mime_type:string,size:string,context:string}>
     */
    private function agreementDocuments(array $agreement): array
    {
        $documents = [];

        foreach ([
            'genetic_resources',
            'traditional_knowledge',
            'providers',
            'project',
            'benefit_sharing',
            'additional_information',
            'additional_documents',
            'applicant_declaration',
        ] as $root) {
            if (array_key_exists($root, $agreement)) {
                $this->collectDocuments($agreement[$root], $root, $documents);
            }
        }

        return $documents;
    }

    /**
     * @param mixed $value
     * @param list<array{name:string,mime_type:string,size:string,context:string}> $documents
     */
    private function collectDocuments($value, string $fieldKey, array &$documents): void
    {
        if (is_array($value) && $this->isDocumentReference($value)) {
            $name = is_scalar($value['name'] ?? null) ? trim((string) $value['name']) : '';

            if ($name !== '') {
                $documents[] = [
                    'name' => $name,
                    'mime_type' => is_scalar($value['mime_type'] ?? null)
                        ? trim((string) $value['mime_type'])
                        : '',
                    'size' => $this->formatFileSize($value['size'] ?? null),
                    'context' => $this->documentContext($fieldKey),
                ];
            }

            return;
        }

        // Older agreements may contain only the original filename. Keep that
        // useful label without exposing an UUID or treating ordinary text as a file.
        if (is_scalar($value) && $this->isLegacyDocumentField($fieldKey, (string) $value)) {
            $documents[] = [
                'name' => trim((string) $value),
                'mime_type' => '',
                'size' => '',
                'context' => $this->documentContext($fieldKey),
            ];

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            $childKey = is_string($key) ? $key : $fieldKey;
            $this->collectDocuments($child, $childKey, $documents);
        }
    }

    /** @param array<int|string, mixed> $value */
    private function isDocumentReference(array $value): bool
    {
        return is_scalar($value['name'] ?? null)
            && trim((string) $value['name']) !== ''
            && (
                array_key_exists('document_uuid', $value)
                || array_key_exists('mime_type', $value)
                || array_key_exists('size', $value)
            );
    }

    private function isLegacyDocumentField(string $fieldKey, string $value): bool
    {
        if (! in_array($fieldKey, ['identification', 'project_summary_attachment', 'attached_document'], true)) {
            return false;
        }

        return 1 === preg_match('/\.(?:pdf|jpe?g|png)$/i', trim($value));
    }

    private function documentContext(string $fieldKey): string
    {
        return [
            'identification' => __('Identification du fournisseur', 'plugin-apa-agadev'),
            'project_summary_attachment' => __('Document du projet', 'plugin-apa-agadev'),
            'attached_document' => __('Document complémentaire', 'plugin-apa-agadev'),
        ][$fieldKey] ?? __('Document joint', 'plugin-apa-agadev');
    }

    /** @param mixed $size */
    private function formatFileSize($size): string
    {
        if (! is_numeric($size) || (int) $size <= 0) {
            return '';
        }

        $bytes = (int) $size;

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', ' ') . ' Mo';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', ' ') . ' Ko';
        }

        return $bytes . ' o';
    }

    /**
     * @param mixed $section
     * @return array<string, mixed>|null
     */
    private function normalizeSection($section): ?array
    {
        if (! is_array($section)) {
            throw new UnexpectedValueException('An agreement presentation section is invalid.');
        }

        $label = $this->requiredLabel($section, 'section');
        $groups = $section['groups'] ?? null;

        if (! is_array($groups)) {
            throw new UnexpectedValueException('An agreement presentation section has invalid groups.');
        }

        $normalizedGroups = [];

        foreach ($groups as $group) {
            $normalized = $this->normalizeGroup($group);

            if ($normalized !== null) {
                $normalizedGroups[] = $normalized;
            }
        }

        if ($normalizedGroups === []) {
            return null;
        }

        return [
            'key' => sanitize_key((string) ($section['key'] ?? '')),
            'label' => $label,
            'groups' => $normalizedGroups,
        ];
    }

    /**
     * @param mixed $group
     * @return array<string, mixed>|null
     */
    private function normalizeGroup($group): ?array
    {
        if (! is_array($group)) {
            throw new UnexpectedValueException('An agreement presentation group is invalid.');
        }

        $label = $this->requiredLabel($group, 'group');
        $entries = $group['entries'] ?? null;

        if (! is_array($entries)) {
            throw new UnexpectedValueException('An agreement presentation group has invalid entries.');
        }

        $normalizedEntries = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_array($entry['fields'] ?? null)) {
                throw new UnexpectedValueException('An agreement presentation entry is invalid.');
            }

            $fields = [];

            foreach ($entry['fields'] as $field) {
                $fields[] = $this->normalizeField($field);
            }

            if ($fields !== []) {
                $normalizedEntries[] = ['fields' => $fields];
            }
        }

        if ($normalizedEntries === []) {
            return null;
        }

        return [
            'key' => sanitize_key((string) ($group['key'] ?? '')),
            'label' => $label,
            'entries' => $normalizedEntries,
        ];
    }

    /**
     * @param mixed $field
     * @return array<string, mixed>
     */
    private function normalizeField($field): array
    {
        if (! is_array($field) || ! array_key_exists('display_value', $field)) {
            throw new UnexpectedValueException('An agreement presentation field is invalid.');
        }

        return [
            'key' => sanitize_key((string) ($field['key'] ?? '')),
            'label' => $this->requiredLabel($field, 'field'),
            // Never fall back to the raw `value`; Maivou owns presentation.
            'display_value' => $this->normalizeDisplayValue($field['display_value']),
        ];
    }

    /**
     * @param mixed $value
     * @return scalar|null|list<scalar|null>
     */
    private function normalizeDisplayValue($value)
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (! is_array($value) || ! $this->isList($value)) {
            throw new UnexpectedValueException('An agreement display value is invalid.');
        }

        $normalized = [];

        foreach ($value as $item) {
            if (! is_scalar($item) && $item !== null) {
                throw new UnexpectedValueException('An agreement display value item is invalid.');
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function requiredLabel(array $node, string $context): string
    {
        $label = isset($node['label']) && is_scalar($node['label'])
            ? trim((string) $node['label'])
            : '';

        if ($label === '') {
            throw new UnexpectedValueException('An agreement presentation ' . $context . ' label is missing.');
        }

        return $label;
    }

    private function statusLabel(string $status): string
    {
        $labels = [
            'draft' => __('Brouillon', 'plugin-apa-agadev'),
            'pending' => __('En attente', 'plugin-apa-agadev'),
            'accepted' => __('Valide', 'plugin-apa-agadev'),
            'rejected' => __('Rejeté', 'plugin-apa-agadev'),
            'expired' => __('Expiré', 'plugin-apa-agadev'),
            'cancelled' => __('Annulé', 'plugin-apa-agadev'),
        ];
        $normalized = strtolower(trim($status));

        return $labels[$normalized] ?? __('Statut indisponible', 'plugin-apa-agadev');
    }

    /** @param mixed $value */
    private function formatDate($value): string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return '—';
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '—' : wp_date('d/m/Y', $timestamp);
    }

    /** @param array<int|string, mixed> $value */
    private function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
