<?php

/**
 * Совместимый фасад старого CodeMatrix\Legacy\Seo\YmlExport.
 * Подключать после Composer autoload и адаптера CodeMatrix\Legacy\Base\XmlExport.
 */

namespace CodeMatrix\Legacy\Seo;

use Project\Export\YmlCatalogRenderer;
use Project\Support\UrlGenerator;

class YmlExport extends \CodeMatrix\Legacy\Base\XmlExport
{
    public function showData(array $params = [])
    {
        // Как и старый класс, showData() выводит уже загруженные данные.
        $config = [
            'shopName' => $this->legacyParams['shopName'] ?? null,
            'companyName' => $this->legacyParams['companyName'] ?? null,
            'platform' => $this->legacyParams['platform'] ?? '1C-Битрикс',
            'currencyId' => $this->legacyParams['currencyId'] ?? 'RUB',
            'currencyRate' => $this->legacyParams['currencyRate'] ?? '1',
        ];

        $renderer = new YmlCatalogRenderer(
            new UrlGenerator($this->serverUrl),
            array_filter($config, static fn ($value) => $value !== null)
        );

        if (!empty($params['sendHeader']) && !headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
        }

        $stream = fopen('php://output', 'wb');
        if (!is_resource($stream)) {
            throw new \\RuntimeException('Не удалось открыть поток вывода YML.');
        }

        try {
            $renderer->renderToStream(
                $this->getSections(),
                $this->getProducts(),
                $stream,
                $params,
                $this->getGeneratedAt()
            );
        } finally {
            fclose($stream);
        }
    }
}
