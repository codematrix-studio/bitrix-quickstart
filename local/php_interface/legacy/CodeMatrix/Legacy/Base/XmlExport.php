<?php

/**
 * Совместимый фасад старого CodeMatrix\Legacy\Base\XmlExport.
 * Подключать после Composer autoload.
 */

namespace CodeMatrix\Legacy\Base;

use InvalidArgumentException;
use LogicException;
use Project\Export\BitrixCatalogExporter;
use Project\Support\UrlGenerator;

class XmlExport extends BitrixCatalogExporter
{
    const DEFAULT_DIR = '/bitrix/catalog_export/export';

    protected $serverUrl = null;
    protected $storageDir = null;
    protected $productsFile = null;
    protected $sectionsFile = null;
    protected $additionalDataFile = null;
    protected $time = null;
    protected $needUpdate = false;
    protected array $legacyParams = [];

    public function __construct($iblockId, array $params = [])
    {
        $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($documentRoot === '') {
            throw new LogicException('Для CodeMatrix\Legacy\Base\XmlExport необходим DOCUMENT_ROOT.');
        }

        $relativeDirectory = (string) ($params['storageDir'] ?? static::DEFAULT_DIR);
        $relativeDirectory = str_replace('\\', '/', $relativeDirectory);
        $segments = explode('/', trim($relativeDirectory, '/'));

        if ($segments === [] || $segments === ['']) {
            throw new InvalidArgumentException('storageDir не может указывать на корень DOCUMENT_ROOT.');
        }

        foreach ($segments as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new InvalidArgumentException('storageDir не должен содержать относительные переходы.');
            }
        }

        $this->storageDir = $documentRoot . '/' . implode('/', array_filter($segments, 'strlen'));

        if (empty($params['serverUrl']) || !is_string($params['serverUrl'])) {
            throw new LogicException(
                'Укажите serverUrl в конфигурации экспорта. Домен нельзя получать из HTTP_HOST.'
            );
        }

        $this->serverUrl = rtrim($params['serverUrl'], '/');
        $this->productsFile = $this->storageDir . '/products.json';
        $this->sectionsFile = $this->storageDir . '/sections.json';
        $this->additionalDataFile = $this->storageDir . '/additionalData.json';
        $this->legacyParams = $params;

        parent::__construct(
            (int) $iblockId,
            new UrlGenerator($this->serverUrl),
            $this->storageDir,
            [
                'sectionIds' => $params['sectionIds'] ?? [],
                'productIds' => $params['productIds'] ?? [],
                'additionalData' => $params['additionalData'] ?? [],
                'cacheTtl' => $params['cacheTtl'] ?? 86400,
                'cacheKey' => $params['cacheKey'] ?? 'codematrix_legacy_catalog_' . (int) $iblockId,
            ]
        );
    }

    public function loadData(array $params = [], $return = false)
    {
        $result = parent::loadData($params, $return);
        $this->time = $this->getGeneratedAt();
        $this->needUpdate = false;

        return $result;
    }

    public function setNeedUpdate($additional): bool
    {
        $this->needUpdate = parent::setNeedUpdate($additional);

        return $this->needUpdate;
    }

    protected function isNeedUpdate(): bool
    {
        return $this->needUpdate || parent::isNeedUpdate();
    }

    /**
     * Старые наследники могли переопределять loadAdditionalData() как источник
     * дополнительных данных. Вызываем такой метод только при обновлении кеша.
     */
    protected function prepareAdditionalData(): void
    {
        $method = new \ReflectionMethod($this, 'loadAdditionalData');

        if ($method->getDeclaringClass()->getName() !== BitrixCatalogExporter::class) {
            $this->loadAdditionalData();
        }
    }

    /**
     * Базовый старый класс не задавал XML-схему и выводил заглушку.
     * Формат должен быть реализован наследником, например YmlExport.
     */
    public function showData(array $params = [])
    {
        throw new LogicException('Для XmlExport необходимо определить формат XML в классе-наследнике.');
    }
}
