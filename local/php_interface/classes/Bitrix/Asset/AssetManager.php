<?php

declare(strict_types=1);

namespace Project\Bitrix\Asset;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Регистрация JavaScript, CSS и расширений ядра Bitrix.
 *
 * По умолчанию использует штатные Bitrix\Main\Page\Asset и CJSCore.
 * Для автономного тестирования зависимости можно передать явно.
 */
class AssetManager
{
    private ?object $asset;
    private ?Closure $extensionInitializer;

    public function __construct(?object $asset = null, ?callable $extensionInitializer = null)
    {
        $this->asset = $asset;
        $this->extensionInitializer = $extensionInitializer === null
            ? null
            : Closure::fromCallable($extensionInitializer);

        if ($this->asset !== null) {
            $this->assertAssetContract($this->asset);
        }
    }

    /**
     * Добавляет один или несколько JavaScript-файлов в очередь Bitrix.
     *
     * @param string|array<int|string, string> $files
     */
    public function addJs(string|array $files): static
    {
        foreach ($this->normalizeFiles($files) as $file) {
            $this->asset()->addJs($file);
        }

        return $this;
    }

    /**
     * Добавляет один или несколько CSS-файлов в очередь Bitrix.
     *
     * @param string|array<int|string, string> $files
     */
    public function addCss(string|array $files): static
    {
        foreach ($this->normalizeFiles($files) as $file) {
            $this->asset()->addCss($file);
        }

        return $this;
    }

    /**
     * Инициализирует расширения, зарегистрированные в CJSCore.
     *
     * @param array<int, string> $extensions
     */
    public function init(array $extensions): static
    {
        foreach ($extensions as $extension) {
            if (!is_string($extension) || trim($extension) === '') {
                throw new InvalidArgumentException('Имена расширений CJSCore должны быть непустыми строками.');
            }
        }

        if ($this->extensionInitializer !== null) {
            ($this->extensionInitializer)($extensions);
            return $this;
        }

        if (!class_exists('CJSCore') || !is_callable(['CJSCore', 'init'])) {
            throw new RuntimeException('Для инициализации расширений необходимо доступное ядро Bitrix с CJSCore::init().');
        }

        \CJSCore::init($extensions);

        return $this;
    }

    /**
     * Совместимость со старым API lema.lib.
     *
     * @param array<int|string, string> $data
     */
    public function addJsArray(array $data = []): static
    {
        return $this->addJs($data);
    }

    /**
     * Совместимость со старым API lema.lib.
     *
     * @param array<int|string, string> $data
     */
    public function addCssArray(array $data = []): static
    {
        return $this->addCss($data);
    }

    private function asset(): object
    {
        if ($this->asset === null) {
            $class = 'Bitrix\Main\Page\Asset';

            if (!class_exists($class) || !is_callable([$class, 'getInstance'])) {
                throw new RuntimeException('Не найден Bitrix\Main\Page\Asset::getInstance(). Подключите ядро Bitrix до регистрации ресурсов.');
            }

            $asset = $class::getInstance();
            $this->assertAssetContract($asset);
            $this->asset = $asset;
        }

        return $this->asset;
    }

    private function assertAssetContract(object $asset): void
    {
        foreach (['addJs', 'addCss'] as $method) {
            if (!is_callable([$asset, $method])) {
                throw new InvalidArgumentException(sprintf('Объект Asset должен предоставлять метод %s().', $method));
            }
        }
    }

    /**
     * @param string|array<int|string, string> $files
     * @return list<string>
     */
    private function normalizeFiles(string|array $files): array
    {
        $files = is_string($files) ? [$files] : array_values($files);

        foreach ($files as $file) {
            if (!is_string($file) || trim($file) === '') {
                throw new InvalidArgumentException('Пути к JS/CSS-файлам должны быть непустыми строками.');
            }
        }

        return $files;
    }
}
