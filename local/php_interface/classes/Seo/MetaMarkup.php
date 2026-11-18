<?php

declare(strict_types=1);

namespace Project\Seo;

use LogicException;
use Project\Support\HtmlEscaper;
use Project\Support\PerClassInstance;

/**
 * Генератор HTML meta-тегов с безопасным экранированием атрибутов.
 */
class MetaMarkup extends PerClassInstance
{
    protected array $info = [];
    protected string $prefix = '';
    protected bool $fromArray = false;

    public function __construct(array $data = [])
    {
        $this->setPrefix();

        if ($data !== []) {
            $this->setItems($data);
        }
    }

    public function setPrefix(): void
    {
    }

    public function setItem($type, $content): static
    {
        if (!is_scalar($type) || (!is_scalar($content) && $content !== null)) {
            throw new LogicException('Название и значение meta-тега должны быть скалярными.');
        }

        $this->info[(string) $type] = (string) $content;

        if (!$this->fromArray) {
            $this->checkItems();
        }

        return $this;
    }

    public function setItems(array $data = []): static
    {
        $this->fromArray = true;

        try {
            foreach ($data as $type => $content) {
                $this->setItem($type, $content);
            }
        } finally {
            $this->fromArray = false;
        }

        $this->checkItems();

        return $this;
    }

    public function setViewContent($name = 'opengraph', $content = null): void
    {
        global $APPLICATION;

        if (!is_object($APPLICATION) || !is_callable([$APPLICATION, 'AddViewContent'])) {
            throw new LogicException('Bitrix $APPLICATION::AddViewContent() недоступен.');
        }

        $APPLICATION->AddViewContent($name, $content ?? $this->getMeta(true));
    }

    public function show($view = 'opengraph'): void
    {
        global $APPLICATION;

        if (!is_object($APPLICATION) || !is_callable([$APPLICATION, 'ShowViewContent'])) {
            throw new LogicException('Bitrix $APPLICATION::ShowViewContent() недоступен.');
        }

        $APPLICATION->ShowViewContent($view);
    }

    public function setImage($image, $view = 'opengraph_img'): static
    {
        $this->setViewContent($view, $image);

        return $this;
    }

    public function getImage($defValue = null, $view = 'opengraph_img')
    {
        global $APPLICATION;

        if (!is_object($APPLICATION) || !is_callable([$APPLICATION, 'GetViewContent'])) {
            throw new LogicException('Bitrix $APPLICATION::GetViewContent() недоступен.');
        }

        $image = $APPLICATION->GetViewContent($view);

        return !is_string($image) || trim($image) === '' ? $defValue : $image;
    }

    public function getItem($type)
    {
        return array_key_exists((string) $type, $this->info) ? $this->info[(string) $type] : false;
    }

    public function getItemMeta($type)
    {
        if (!array_key_exists((string) $type, $this->info)) {
            return false;
        }

        return $this->format((string) $type, $this->info[(string) $type]);
    }

    public function getMeta($return = false)
    {
        $result = '';

        foreach ($this->info as $type => $content) {
            $result .= $this->format((string) $type, (string) $content) . PHP_EOL;
        }

        $result .= PHP_EOL;

        if ($return) {
            return $result;
        }

        echo $result;
    }

    protected function checkItems(): void
    {
        if (array_key_exists('title', $this->info) && array_key_exists('title2', $this->info)) {
            if ($this->info['title'] === '' && $this->info['title2'] !== '') {
                $this->info['title'] = $this->info['title2'];
            }

            unset($this->info['title2']);
        }
    }

    protected function format($type, $content): string
    {
        $escaper = new HtmlEscaper();
        $name = $escaper->escape($this->prefix . (string) $type);
        $value = $escaper->escape((string) $content);

        return '<meta name="' . $name . '" content="' . $value . '" />';
    }
}
