<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/HLBlock/HlBlockEntity.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/HLBlock/HlBlockRepository.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/HLBlock/HlBlockManager.php';

use Project\Bitrix\HLBlock\HlBlockManager;
use Project\Bitrix\HLBlock\HlBlockRepository;

function hlBlockExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class HlBlockFakeResult
{
    /** @param array<string, mixed>|null $row */
    public function __construct(private ?array $row = null) {}

    public function fetch(): ?array
    {
        $row = $this->row;
        $this->row = null;

        return $row;
    }
}

class HlBlockFakeOperationResult
{
    public function __construct(private bool $success, private int $id = 0) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /** @return list<string> */
    public function getErrorMessages(): array
    {
        return $this->success ? [] : ['Ошибка тестовой операции.'];
    }
}

class HlBlockFakeDataClass
{
    public static array $calls = [];

    public static function getList(array $parameters): HlBlockFakeResult
    {
        self::$calls[] = ['getList', $parameters];

        return new HlBlockFakeResult(['ID' => 15, 'UF_NAME' => 'Тестовая запись']);
    }

    public static function add(array $fields): HlBlockFakeOperationResult
    {
        self::$calls[] = ['add', $fields];

        return new HlBlockFakeOperationResult(true, 25);
    }

    public static function update(int $id, array $fields): HlBlockFakeOperationResult
    {
        self::$calls[] = ['update', $id, $fields];

        return new HlBlockFakeOperationResult(true, $id);
    }

    public static function delete(int $id): HlBlockFakeOperationResult
    {
        self::$calls[] = ['delete', $id];

        return new HlBlockFakeOperationResult(true, $id);
    }
}

class HlBlockFakeCompiledEntity
{
    public function getDataClass(): string
    {
        return HlBlockFakeDataClass::class;
    }
}

$moduleLoads = 0;
$metadataLoads = 0;
$compilations = 0;

$repository = new HlBlockRepository(
    static function () use (&$moduleLoads): bool {
        $moduleLoads++;

        return true;
    },
    static function (int $id) use (&$metadataLoads): ?array {
        $metadataLoads++;

        return $id === 12
            ? ['ID' => 12, 'NAME' => 'Тестовый блок', 'TABLE_NAME' => 'b_hlbd_test']
            : null;
    },
    static function (array $metadata) use (&$compilations): object {
        $compilations++;

        return new HlBlockFakeCompiledEntity();
    }
);

$manager = new HlBlockManager($repository);
$hlBlock = $manager->getById(12);

hlBlockExpect($hlBlock->getId() === 12, 'Менеджер должен вернуть сущность с запрошенным ID.');
hlBlockExpect($hlBlock->getMetadata()['TABLE_NAME'] === 'b_hlbd_test', 'Метаданные блока должны быть доступны.');
hlBlockExpect($hlBlock->getDataClass() === HlBlockFakeDataClass::class, 'Должен возвращаться скомпилированный ORM-класс.');

$again = $manager->getById(12);
hlBlockExpect($again->getId() === 12, 'Повторное получение должно возвращать сущность того же блока.');
hlBlockExpect($metadataLoads === 1, 'Метаданные одного блока должны кешироваться в репозитории.');
hlBlockExpect($compilations === 1, 'ORM-сущность одного блока должна компилироваться один раз.');
hlBlockExpect($moduleLoads === 2, 'Доступность модуля должна проверяться перед каждым получением блока.');

$item = $hlBlock->getById(15);
hlBlockExpect($item === ['ID' => 15, 'UF_NAME' => 'Тестовая запись'], 'getById() должен возвращать строку результата.');

$itemByXmlId = $hlBlock->findByXmlId('external-15');
hlBlockExpect($itemByXmlId !== null, 'findByXmlId() должен возвращать найденную запись.');

$addResult = $hlBlock->add(['UF_NAME' => 'Новая запись']);
hlBlockExpect($addResult->isSuccess() && $addResult->getId() === 25, 'add() должен возвращать штатный результат ORM.');

$updateResult = $hlBlock->update(15, ['UF_NAME' => 'Обновлённая запись']);
hlBlockExpect($updateResult->isSuccess(), 'update() должен возвращать штатный результат ORM.');

$deleteResult = $hlBlock->delete(15);
hlBlockExpect($deleteResult->isSuccess(), 'delete() должен возвращать штатный результат ORM.');

$invalidIdFailed = false;
try {
    $manager->getById(0);
} catch (InvalidArgumentException) {
    $invalidIdFailed = true;
}
hlBlockExpect($invalidIdFailed, 'Нулевой ID highload-блока должен отклоняться.');

$missingBlockFailed = false;
try {
    $manager->getById(99);
} catch (RuntimeException $exception) {
    $missingBlockFailed = str_contains($exception->getMessage(), 'не найден');
}
hlBlockExpect($missingBlockFailed, 'Для отсутствующего HL-блока должно выбрасываться понятное исключение.');

$missingModuleFailed = false;
try {
    (new HlBlockManager(new HlBlockRepository(
        static fn (): bool => false,
        static fn (int $id): ?array => null,
        static fn (array $metadata): object => new HlBlockFakeCompiledEntity()
    )))->getById(12);
} catch (RuntimeException $exception) {
    $missingModuleFailed = str_contains($exception->getMessage(), 'highloadblock');
}
hlBlockExpect($missingModuleFailed, 'При недоступном модуле должно выбрасываться понятное исключение.');

$emptyAddFailed = false;
try {
    $hlBlock->add([]);
} catch (InvalidArgumentException) {
    $emptyAddFailed = true;
}
hlBlockExpect($emptyAddFailed, 'Добавление пустой записи должно отклоняться.');

$emptyUpdateFailed = false;
try {
    $hlBlock->update(15, []);
} catch (InvalidArgumentException) {
    $emptyUpdateFailed = true;
}
hlBlockExpect($emptyUpdateFailed, 'Обновление пустой записью должно отклоняться.');

$emptyXmlIdFailed = false;
try {
    $hlBlock->findByXmlId(' ');
} catch (InvalidArgumentException) {
    $emptyXmlIdFailed = true;
}
hlBlockExpect($emptyXmlIdFailed, 'Пустой внешний идентификатор должен отклоняться.');

echo "Проверки HLBlock пройдены.\n";
