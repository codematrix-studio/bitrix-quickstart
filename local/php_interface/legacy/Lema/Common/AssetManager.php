<?php

declare(strict_types=1);

namespace Lema\\Common;

use Project\\Bitrix\\Asset\\AssetManager as BitrixAssetManager;

/** Переходный адаптер старого Lema\\Common\\AssetManager. */
class AssetManager
{
    private BitrixAssetManager $manager;

    public function __construct()
    {
        $this->manager = new BitrixAssetManager(Asset::get());
    }

    /** @param array<int|string, string> $data */
    public function addJsArray(array $data = []): self
    {
        $this->manager->addJs($data);

        return $this;
    }

    /** @param array<int|string, string> $data */
    public function addCssArray(array $data = []): self
    {
        $this->manager->addCss($data);

        return $this;
    }

    /** @param array<int|string, string> $data */
    public function init(array $data = []): self
    {
        $this->manager->init($data);

        return $this;
    }
}
