<?php

declare(strict_types=1);

namespace Project\\Bitrix\\Asset;

use Closure;
use InvalidArgumentException;
use LogicException;

/** Фасад над штатным механизмом регистрации ресурсов Bitrix. */
final class AssetManager
{
    private ?Closure $extensionInitializer;

    /**
     * @param object $asset Объект Bitrix\\Main\\Page\\Asset или совместимый объект.
     */
    public function __construct(
        private object $asset,
        ?callable $extensionInitializer = null
    ) {
        $this->extensionInitializer = $extensionInitializer === null
            ? null
            : Closure::fromCallable($extensionInitializer);
    }

    /** @param string|array<int|string, string> $files */
    public function addJs(string|array $files): self
    {
        foreach ($this->normalizeFiles($files) as $file) {
            $this->callAssetMethod('addJs', $file);
        }

        return $this;
    }

    /** @param string|array<int|string, string> $files */
    public function addCss(string|array $files): self
    {
        foreach ($this->normalizeFiles($files) as $file) {
            $this->callAssetMethod('addCss', $file);
        }

        return $this;
    }

    /** @param array<int|string, string> $extensions */
    public function init(array $extensions): self
    {
        $extensions = $this->normalizeFiles($extensions);

        if ($this->extensionInitializer !== null) {
            ($this->extensionInitializer)($extensions);

            return $this;
        }

        if (!class_exists('CJSCore') || !is_callable(['CJSCore', 'init'])) {
            throw new LogicException('Для инициализации расширений требуется доступный CJSCore::init().');
        }

        \\CJSCore::init($extensions);

        return $this;
    }

    /** @param string|array<int|string, string> $files
     *  @return list<string>
     */
    private function normalizeFiles(string|array $files): array
    {
        $files = is_string($files) ? [$files] : array_values($files);

        foreach ($files as $file) {
            if (!is_string($file) || trim($file) === '') {
                throw new InvalidArgumentException('Пути ресурсов и имена расширений должны быть непустыми строками.');
            }
        }

        return $files;
    }

    private function callAssetMethod(string $method, string $file): void
    {
        if (!is_callable([$this->asset, $method])) {
            throw new LogicException(sprintf('Объект Asset не поддерживает метод %s().', $method));
        }

        $this->asset->{$method}($file);
    }
}
