<?php

declare(strict_types=1);

namespace Project\Export;

use Closure;
use InvalidArgumentException;
use Project\Bitrix\IBlock\ElementRepository;
use Project\Bitrix\IBlock\SectionRepository;
use Project\Support\JsonFileCache;
use Project\Support\UrlGenerator;
use RuntimeException;

/**
 * Загружает данные инфоблока и кеширует подготовленный каталог.
 *
 * Класс отделяет получение данных Битрикс от формирования YML.
 * Абсолютные URL строятся только от доверенного UrlGenerator.
 */
class BitrixCatalogExporter
{
    protected int $iblockId;
    protected array $products = [];
    protected array $sections = [];
    protected array $additionalData = [];
    protected int $generatedAt = 0;
    protected bool $forceUpdate = false;

    protected UrlGenerator $urls;
    protected JsonFileCache $cache;
    protected ElementRepository $elementRepository;
    protected SectionRepository $sectionRepository;

    protected array $sectionIds = [];
    protected array $productIds = [];
    protected int $cacheTtl = 86400;
    protected string $cacheKey = 'catalog';

    private ?Closure $picturePathResolver;

    public function __construct(
        int $iblockId,
        UrlGenerator $urls,
        string $storageDirectory,
        array $config = [],
        ?ElementRepository $elementRepository = null,
        ?SectionRepository $sectionRepository = null,
        ?JsonFileCache $cache = null,
        ?callable $picturePathResolver = null
    ) {
        if ($iblockId < 1) {
            throw new InvalidArgumentException('ID инфоблока должен быть положительным числом.');
        }

        $this->iblockId = $iblockId;
        $this->urls = $urls;
        $this->cache = $cache ?? new JsonFileCache($storageDirectory);
        $this->elementRepository = $elementRepository ?? new ElementRepository();
        $this->sectionRepository = $sectionRepository ?? new SectionRepository();

        $this->sectionIds = array_values(array_filter(
            array_map('intval', (array) ($config['sectionIds'] ?? [])),
            static fn (int $id): bool => $id > 0
        ));
        $this->productIds = array_values(array_filter(
            array_map('intval', (array) ($config['productIds'] ?? [])),
            static fn (int $id): bool => $id > 0
        ));
        $this->additionalData = is_array($config['additionalData'] ?? null)
            ? $config['additionalData']
            : [];

        $this->cacheTtl = (int) ($config['cacheTtl'] ?? 86400);
        if ($this->cacheTtl < 0) {
            throw new InvalidArgumentException('Время жизни кеша не может быть отрицательным.');
        }

        $this->cacheKey = (string) ($config['cacheKey'] ?? 'catalog');
        $this->picturePathResolver = $picturePathResolver === null
            ? null
            : Closure::fromCallable($picturePathResolver);
    }

    public function setNeedUpdate($additional): bool
    {
        if ($additional) {
            $this->forceUpdate = true;
        }

        return $this->forceUpdate;
    }

    public function loadAdditionalData(array $additionalData = []): void
    {
        $this->additionalData = $additionalData;
        $this->forceUpdate = true;
    }

    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }

    /**
     * Загружает каталог из кеша или инфоблока.
     *
     * При $return=true возвращает массив sections/products; иначе сохраняет
     * старое поведение и ничего не возвращает.
     */
    public function loadData(array $params = [], $return = false)
    {
        $cached = null;

        if (!$this->forceUpdate && $this->cache->isFresh($this->cacheKey, $this->cacheTtl)) {
            $cached = $this->cache->get($this->cacheKey);
        }

        if ($this->isValidCache($cached)) {
            $this->products = $cached['products'];
            $this->sections = $cached['sections'];
            $this->additionalData = $cached['additionalData'] ?? $this->additionalData;
            $this->generatedAt = (int) ($cached['generatedAt'] ?? 0);
        } else {
            $this->sections = $this->loadSections();
            $this->products = $this->loadProducts($params);
            $this->generatedAt = time();

            $this->cache->put($this->cacheKey, [
                'generatedAt' => $this->generatedAt,
                'products' => $this->products,
                'sections' => $this->sections,
                'additionalData' => $this->additionalData,
            ]);
        }

        $this->forceUpdate = false;

        if ($return) {
            return [
                'sections' => $this->sections,
                'products' => $this->products,
            ];
        }

        return null;
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    public function getSections(): array
    {
        return $this->sections;
    }

    public function getGeneratedAt(): int
    {
        return $this->generatedAt;
    }

    /**
     * Оставлен protected, чтобы старые наследники могли переопределять загрузку разделов.
     */
    protected function loadSections(): array
    {
        if ($this->sectionIds === []) {
            return [];
        }

        $roots = $this->sectionRepository->getListIncludingInactive($this->iblockId, [
            'order' => ['LEFT_MARGIN' => 'ASC'],
            'filter' => ['ID' => $this->sectionIds],
            'arSelect' => ['ID', 'NAME', 'LEFT_MARGIN', 'RIGHT_MARGIN', 'IBLOCK_SECTION_ID'],
        ]);

        $sections = [];

        foreach ($roots as $root) {
            if (!is_array($root) || !isset($root['ID'])) {
                continue;
            }

            $rootId = (int) $root['ID'];
            $sections[$rootId] = ['name' => (string) ($root['NAME'] ?? '')];

            if (!isset($root['LEFT_MARGIN'], $root['RIGHT_MARGIN'])) {
                continue;
            }

            $children = $this->sectionRepository->getListIncludingInactive($this->iblockId, [
                'order' => ['LEFT_MARGIN' => 'ASC'],
                'filter' => [
                    '>LEFT_MARGIN' => (int) $root['LEFT_MARGIN'],
                    '<RIGHT_MARGIN' => (int) $root['RIGHT_MARGIN'],
                    '!ID' => $rootId,
                ],
                'arSelect' => ['ID', 'NAME', 'IBLOCK_SECTION_ID'],
            ]);

            foreach ($children as $child) {
                if (!is_array($child) || !isset($child['ID'])) {
                    continue;
                }

                $childId = (int) $child['ID'];
                $sections[$childId] = [
                    'name' => (string) ($child['NAME'] ?? ''),
                    'parentId' => (int) ($child['IBLOCK_SECTION_ID'] ?? $rootId),
                ];
            }
        }

        return $sections;
    }

    protected function loadProducts(array $params = []): array
    {
        $products = $this->elementRepository->getAll($this->iblockId, $params);

        if ($this->productIds !== []) {
            $allowedIds = array_fill_keys($this->productIds, true);
            $products = array_filter(
                $products,
                static fn ($product, $id): bool => isset($allowedIds[(int) $id]),
                ARRAY_FILTER_USE_BOTH
            );
        }

        if (isset($params['limit'])) {
            $limit = (int) $params['limit'];
            if ($limit > 0) {
                $products = array_slice($products, 0, $limit, true);
            }
        }

        $callback = $params['callback'] ?? null;
        if ($callback !== null && !is_callable($callback)) {
            throw new InvalidArgumentException('Параметр callback должен быть вызываемой функцией.');
        }

        $result = [];

        foreach ($products as $id => $product) {
            if (!is_array($product)) {
                continue;
            }

            $product['PREVIEW_PICTURE'] = $this->resolvePictureUrl($product['PREVIEW_PICTURE'] ?? null);

            if (!empty($product['PREVIEW_PAGE_URL'])) {
                $product['PREVIEW_PAGE_URL'] = $this->urls->absolute((string) $product['PREVIEW_PAGE_URL']);
            }

            $product['PREVIEW_TEXT'] = $this->plainText($product['PREVIEW_TEXT'] ?? '');
            $product['DETAIL_TEXT'] = $this->plainText($product['DETAIL_TEXT'] ?? '');

            if ($callback !== null) {
                $product = $callback($product);
                if (!is_array($product)) {
                    throw new RuntimeException('Callback экспорта должен возвращать массив товара.');
                }
            }

            $productId = $product['ID'] ?? $id;
            $result[$productId] = $product;
        }

        return $result;
    }

    protected function isNeedUpdate(): bool
    {
        return $this->forceUpdate
            || !$this->cache->isFresh($this->cacheKey, $this->cacheTtl)
            || !$this->isValidCache($this->cache->get($this->cacheKey));
    }

    private function isValidCache($data): bool
    {
        return is_array($data)
            && isset($data['products'], $data['sections'])
            && is_array($data['products'])
            && is_array($data['sections']);
    }

    private function resolvePictureUrl($picture): string
    {
        if ($picture === null || $picture === '' || $picture === false || $picture === 0 || $picture === '0') {
            return '';
        }

        if (is_string($picture) && str_starts_with($picture, '/')) {
            return $this->urls->absolute($picture);
        }

        if (!is_numeric($picture) || (int) $picture < 1) {
            throw new InvalidArgumentException('PREVIEW_PICTURE должен содержать ID файла или относительный путь.');
        }

        if ($this->picturePathResolver !== null) {
            $path = ($this->picturePathResolver)((int) $picture);
        } elseif (class_exists('CFile') && is_callable(['CFile', 'GetPath'])) {
            $path = \CFile::GetPath((int) $picture);
        } else {
            throw new RuntimeException('Для преобразования PREVIEW_PICTURE требуется CFile::GetPath().');
        }

        if (!is_string($path) || $path === '') {
            return '';
        }

        return $this->urls->absolute($path);
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
}
