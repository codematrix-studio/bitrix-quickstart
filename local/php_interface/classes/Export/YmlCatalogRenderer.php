<?php

declare(strict_types=1);

namespace Project\Export;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Project\Support\UrlGenerator;

/**
 * Формирует YML-каталог из уже подготовленных массивов разделов и товаров.
 *
 * Не обращается к Битрикс, файловой системе или HTTP-запросу.
 * Базовый URL должен быть задан доверенной конфигурацией.
 */
final class YmlCatalogRenderer
{
    private UrlGenerator $urls;
    private string $shopName;
    private string $companyName;
    private string $platform;
    private string $currencyId;
    private string $currencyRate;

    public function __construct(UrlGenerator $urls, array $config = [])
    {
        $this->urls = $urls;
        $trustedHost = (string) (parse_url($urls->getBaseUrl(), PHP_URL_HOST) ?: '');

        $this->shopName = (string) ($config['shopName'] ?? $trustedHost);
        $this->companyName = (string) ($config['companyName'] ?? $this->shopName);
        $this->platform = (string) ($config['platform'] ?? '1C-Битрикс');
        $this->currencyId = (string) ($config['currencyId'] ?? 'RUB');
        $this->currencyRate = (string) ($config['currencyRate'] ?? '1');
    }

    /**
     * @param array $sections Разделы, индексированные по ID.
     * @param array $products Товары, индексированные по ID.
     * @param array $params Дополнительные поля и параметры YML.
     * @param DateTimeInterface|int|null $generatedAt Время формирования каталога.
     */
    public function render(
        array $sections,
        array $products,
        array $params = [],
        $generatedAt = null
    ): string {
        $timestamp = $this->normalizeTimestamp($generatedAt);
        $date = date('Y-m-d H:i', $timestamp);
        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">',
            '<yml_catalog date="' . XmlEscaper::attribute($date) . '">',
            '<shop>',
            '<name>' . XmlEscaper::text($this->shopName) . '</name>',
            '<company>' . XmlEscaper::text($this->companyName) . '</company>',
            '<url>' . XmlEscaper::text($this->urls->getBaseUrl() . '/') . '</url>',
            '<platform>' . XmlEscaper::text($this->platform) . '</platform>',
            '<currencies>',
            '<currency id="' . XmlEscaper::attribute($this->currencyId) . '" rate="' . XmlEscaper::attribute($this->currencyRate) . '" />',
            '</currencies>',
            '<categories>',
        ];

        foreach ($sections as $sectionId => $section) {
            if (!is_array($section) || !isset($section['name'])) {
                continue;
            }

            $xml[] = '<category id="' . XmlEscaper::attribute($sectionId) . '"'
                . (isset($section['parentId'])
                    ? ' parentId="' . XmlEscaper::attribute($section['parentId']) . '"'
                    : '')
                . '>' . XmlEscaper::text($section['name']) . '</category>';
        }

        $xml[] = '</categories>';
        $xml[] = '<offers>';

        $fields = $this->normalizeFields($params['fields'] ?? []);
        $additionalParams = $this->normalizeParams($params['params'] ?? []);

        foreach ($products as $productId => $product) {
            if (!is_array($product)) {
                continue;
            }

            $id = $product['ID'] ?? $productId;
            $available = ($product['ACTIVE'] ?? null) === 'Y'
                && ($product['CATALOG_AVAILABLE'] ?? null) === 'Y';

            $xml[] = '<offer id="' . XmlEscaper::attribute($id) . '" available="' . ($available ? 'true' : 'false') . '">';

            $productUrl = $this->absoluteUrl($product['DETAIL_PAGE_URL'] ?? null, true);
            if ($productUrl !== null) {
                $xml[] = '<url>' . XmlEscaper::text($productUrl) . '</url>';
            }

            $pictureUrl = $this->absoluteUrl($product['PREVIEW_PICTURE'] ?? null);
            if ($pictureUrl !== null) {
                $xml[] = '<picture>' . XmlEscaper::text($pictureUrl) . '</picture>';
            }

            $xml[] = '<name>' . XmlEscaper::text($product['NAME'] ?? '') . '</name>';

            foreach ($fields as $tag => $field) {
                if (!array_key_exists($field, $product) || $product[$field] === null) {
                    continue;
                }

                $xml[] = '<' . $tag . '>' . XmlEscaper::text($this->valueToString($product[$field])) . '</' . $tag . '>';
            }

            if (isset($product['IBLOCK_SECTION_ID']) && is_scalar($product['IBLOCK_SECTION_ID'])) {
                $xml[] = '<categoryId>' . XmlEscaper::text($product['IBLOCK_SECTION_ID']) . '</categoryId>';
            }

            $alternateDescription = [];
            foreach ($additionalParams as $item) {
                $field = $item['field'];
                if (!array_key_exists($field, $product) || $product[$field] === null || $product[$field] === '') {
                    continue;
                }

                $value = $this->valueToString($product[$field]);
                $label = $item['name'];
                $alternateDescription[] = $label . ': ' . $value;

                $unit = isset($item['unit'])
                    ? ' unit="' . XmlEscaper::attribute($item['unit']) . '"'
                    : '';

                $xml[] = '<param name="' . XmlEscaper::attribute($label) . '"' . $unit . '>'
                    . XmlEscaper::text($value)
                    . '</param>';
            }

            $description = $this->plainText(
                !empty($product['DETAIL_TEXT'])
                    ? $product['DETAIL_TEXT']
                    : ($product['PREVIEW_TEXT'] ?? '')
            );

            if ($description === '' && $alternateDescription !== []) {
                $description = implode('; ', $alternateDescription);
            }

            if ($description !== '') {
                $xml[] = '<description>' . XmlEscaper::text($description) . '</description>';
            }

            $xml[] = '</offer>';
        }

        $xml[] = '</offers>';
        $xml[] = '</shop>';
        $xml[] = '</yml_catalog>';

        return implode("\n", $xml) . "\n";
    }

    private function normalizeTimestamp($generatedAt): int
    {
        if ($generatedAt instanceof DateTimeInterface) {
            return $generatedAt->getTimestamp();
        }

        if (is_int($generatedAt) && $generatedAt > 0) {
            return $generatedAt;
        }

        if ($generatedAt !== null) {
            throw new InvalidArgumentException('Время формирования YML должно быть timestamp или DateTimeInterface.');
        }

        return (new DateTimeImmutable())->getTimestamp();
    }

    private function normalizeFields($fields): array
    {
        if (!is_array($fields)) {
            throw new InvalidArgumentException('Параметр fields должен быть массивом.');
        }

        $normalized = [];
        foreach ($fields as $tag => $field) {
            $tag = (string) $tag;
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/D', $tag) !== 1
                || preg_match('/^xml/i', $tag) === 1
            ) {
                throw new InvalidArgumentException('Недопустимое имя XML-тега: ' . $tag);
            }

            if (!is_string($field) || $field === '') {
                throw new InvalidArgumentException('Для XML-тега ' . $tag . ' не задано имя поля товара.');
            }

            // Служебные элементы YML формируются самим классом.
            if (in_array($tag, ['url', 'picture', 'name', 'categoryId', 'description', 'param'], true)) {
                throw new InvalidArgumentException('Поле ' . $tag . ' зарезервировано форматом YML.');
            }

            $normalized[$tag] = $field;
        }

        return $normalized;
    }

    private function normalizeParams($params): array
    {
        if (!is_array($params)) {
            throw new InvalidArgumentException('Параметр params должен быть массивом.');
        }

        $normalized = [];
        foreach ($params as $item) {
            if (!is_array($item) || !isset($item[0], $item[1])) {
                continue;
            }

            $normalized[] = [
                'name' => (string) $item[0],
                'field' => (string) $item[1],
                'unit' => isset($item['unit']) ? (string) $item['unit'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * Разрешает только относительные ссылки или абсолютные ссылки того же origin.
     */
    private function absoluteUrl($value, bool $appendTrailingSlash = false): ?string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        $base = parse_url($this->urls->getBaseUrl());
        $parts = parse_url($value);

        if ($parts === false) {
            throw new InvalidArgumentException('Некорректный URL в данных товара.');
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            $baseScheme = strtolower((string) ($base['scheme'] ?? ''));
            $baseHost = strtolower((string) ($base['host'] ?? ''));
            $urlScheme = strtolower((string) ($parts['scheme'] ?? ''));
            $urlHost = strtolower((string) ($parts['host'] ?? ''));
            $defaultPort = $baseScheme === 'https' ? 443 : 80;
            $basePort = (int) ($base['port'] ?? $defaultPort);
            $urlPort = (int) ($parts['port'] ?? ($urlScheme === 'https' ? 443 : 80));

            if ($urlScheme !== $baseScheme
                || $urlHost !== $baseHost
                || $urlPort !== $basePort
                || isset($parts['user'])
                || isset($parts['pass'])
            ) {
                throw new InvalidArgumentException('Внешний URL товара не соответствует доверенному домену каталога.');
            }

            $path = $parts['path'] ?? '/';
            if ($appendTrailingSlash && !isset($parts['query']) && !isset($parts['fragment'])) {
                $path = rtrim($path, '/') . '/';
            }

            return $urlScheme . '://' . $urlHost
                . (isset($parts['port']) ? ':' . (int) $parts['port'] : '')
                . $path
                . (isset($parts['query']) ? '?' . $parts['query'] : '')
                . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
        }

        if ($appendTrailingSlash && !str_contains($value, '?') && !str_contains($value, '#')) {
            $value = rtrim($value, '/') . '/';
        }

        return $this->urls->absolute($value);
    }

    private function plainText($value): string
    {
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }

        $value = strip_tags((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\r\n\t ]+/u', ' ', $value);

        return trim($value ?? '');
    }

    private function valueToString($value): string
    {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item instanceof \Stringable) {
                    $values[] = (string) $item;
                }
            }

            return implode(', ', $values);
        }

        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
