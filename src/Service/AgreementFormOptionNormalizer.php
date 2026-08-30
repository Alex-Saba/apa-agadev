<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

/**
 * Converts the option formats exposed by Maivou into HTML value/label pairs.
 */
final class AgreementFormOptionNormalizer
{
    /**
     * @param mixed $items
     * @return array<string, string>
     */
    public static function normalize($items, string $endpoint = ''): array
    {
        if (is_object($items)) {
            $items = get_object_vars($items);
        }

        if (! is_array($items)) {
            $items = is_scalar($items) ? [$items] : [];
        }

        $options = [];
        $isList = self::isList($items);

        foreach ($items as $key => $item) {
            if ($isList) {
                [$value, $label] = self::listItem($item, $endpoint);
            } else {
                // Maivou's associative options already use the technical value as key.
                $value = self::stringValue($key);
                $label = self::labelValue($item);
            }

            if ('' === $value) {
                continue;
            }

            $options[$value] = '' !== $label ? $label : $value;
        }

        return $options;
    }

    /**
     * Resolves stored identifiers to the canonical value expected by Maivou.
     *
     * Older drafts may contain a product code or a zone UUID written by a
     * previous plugin version. Matching every known identifier lets the form
     * select the corresponding option and submit the canonical value again.
     *
     * @param mixed $selected
     * @param mixed $items
     * @return list<string>
     */
    public static function normalizeSelected($selected, $items, string $endpoint = ''): array
    {
        $selectedValues = is_array($selected) ? $selected : [$selected];
        $selectedValues = array_values(array_filter(
            array_map([self::class, 'stringValue'], $selectedValues),
            static fn (string $value): bool => '' !== $value
        ));

        if ($selectedValues === []) {
            return [];
        }

        if (is_object($items)) {
            $items = get_object_vars($items);
        }

        if (! is_array($items) || ! self::isList($items)) {
            return $selectedValues;
        }

        $resolved = [];

        foreach ($selectedValues as $selectedValue) {
            $canonicalValue = $selectedValue;

            foreach ($items as $item) {
                if (is_object($item)) {
                    $item = get_object_vars($item);
                }

                if (! is_array($item) || ! in_array($selectedValue, self::itemIdentifiers($item), true)) {
                    continue;
                }

                [$candidate] = self::listItem($item, $endpoint);
                if ('' !== $candidate) {
                    $canonicalValue = $candidate;
                }
                break;
            }

            if (! in_array($canonicalValue, $resolved, true)) {
                $resolved[] = $canonicalValue;
            }
        }

        return $resolved;
    }

    /** @return array{0:string,1:string} */
    private static function listItem($item, string $endpoint = ''): array
    {
        if (is_scalar($item) || $item instanceof \Stringable) {
            $value = self::stringValue($item);

            return [$value, $value];
        }

        if (is_object($item)) {
            $item = get_object_vars($item);
        }

        if (! is_array($item)) {
            return ['', ''];
        }

        $value = self::firstValue($item, self::valuePriority($endpoint));
        $label = self::firstValue($item, ['label', 'name', 'title', 'code', 'value']);

        if ('' === $label) {
            $label = self::geographicLabel($item);
        }

        return [$value, $label];
    }

    /** @return list<string> */
    private static function valuePriority(string $endpoint): array
    {
        if ('/api/products' === $endpoint) {
            return ['value', 'uuid', 'code', 'id', 'key'];
        }

        if ('/api/zones' === $endpoint) {
            return ['value', 'id', 'uuid', 'code', 'key'];
        }

        return ['value', 'code', 'uuid', 'id', 'key'];
    }

    /** @return list<string> */
    private static function itemIdentifiers(array $item): array
    {
        $identifiers = [];

        foreach (['value', 'code', 'uuid', 'id', 'key'] as $key) {
            if (! array_key_exists($key, $item)) {
                continue;
            }

            $identifier = self::stringValue($item[$key]);
            if ('' !== $identifier && ! in_array($identifier, $identifiers, true)) {
                $identifiers[] = $identifier;
            }
        }

        return $identifiers;
    }

    private static function labelValue($item): string
    {
        if (is_scalar($item) || $item instanceof \Stringable) {
            return self::stringValue($item);
        }

        if (is_object($item)) {
            $item = get_object_vars($item);
        }

        if (! is_array($item)) {
            return '';
        }

        $label = self::firstValue($item, ['label', 'name', 'title', 'code', 'value']);

        return '' !== $label ? $label : self::geographicLabel($item);
    }

    /**
     * Builds the human-readable label exposed by Maivou zone records.
     */
    private static function geographicLabel(array $item): string
    {
        $parts = [];

        foreach (['province_name', 'department_name', 'department_capital_name'] as $key) {
            if (! array_key_exists($key, $item)) {
                continue;
            }

            $part = self::stringValue($item[$key]);
            if ('' !== $part && ! in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }

        return implode(' — ', $parts);
    }

    /** @param list<string> $keys */
    private static function firstValue(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $item)) {
                continue;
            }

            $value = self::stringValue($item[$key]);
            if ('' !== $value) {
                return $value;
            }
        }

        return '';
    }

    private static function stringValue($value): string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return '';
        }

        return trim((string) $value);
    }

    private static function isList(array $items): bool
    {
        return [] === $items || array_keys($items) === range(0, count($items) - 1);
    }
}
