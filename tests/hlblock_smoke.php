<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/HLBlock/HlBlockEntity.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/HLBlock/HlBlockManager.php';

function hlBlockExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class FakeHlBlockQueryResult
{
    public function __construct(private array $rows) {}

    public function fetchAll(): array
    {
        return $this->rows;
    }

    public function fetch(): ?array
    {
        return $this->rows[0] ?? null;
    }
}

class FakeHlBlockOperationResult
{
    public function __construct(
        private bool $success = true,
        private int $id = 0,
        private array $errors = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getErrorMessages(): array
    {
        return $this->errors;
    }
}

class FakeHlBlockDataClass
{
    public static array $rows = [
        1 => ['ID' => 1, 'UF_XML_ID' => 'first', 'UF_NAME' => 'Первая запись'],
    ];

    public static function getList(array $parameters): FakeHlBlockQueryResult
    {
        return new FakeHlBlockQueryResult(array_values(self::$rows));
    }

    public static function getByPrimary(int $id, array $parameters): FakeHlBlockQueryResult
    {
        return new FakeHlBlockQueryResult(isset(self::$rows[$id]) ? [self::$rows[$id]] : []);
    }

    public static function add(array $fields): FakeHlBlockOperationResult
    {
        $id = max(array_keys(self::$rows) ?: [0]) + 1;
        self::$rows[$id] = ['ID' => $id] + $fields;

        return new FakeHlBlockOperationResult(true, $id);
    }

    public static function update(int $id, array $fields): FakeHlBlockOperationResult
    {
        if (!isset(self::$rows[$id])) {
            return new FakeHlBlockOperationResult(false, 0, ['Запись не найдена']);
        }

        self::$rows[$id] = array_replace(self::$rows[$id], $fields);

        return new FakeHlBlockOperationResult();
    }

    public static function delete(int $id): FakeHlBlockOperationResult
    {
        unset(self::$rows[$id]);

        return new FakeHlBlockOperationResult();
    }
}

$moduleLoads = 0;
$blockLookups = 0;
$manager = new \Project\Bitrix\HLBlock\HlBlockManager(
    static function () use (&$moduleLoads): bool {
        $moduleLoads++;

        return true;
    },
    static function (int $id) use (&$blockLookups): ?array {
        $blockLookups++;

        return $id === 7 ? ['ID' => 7, 'NAME' => 'Тестовый блок'] : null;
    },
    static fn (array $block): string => FakeHlBlockDataClass::class
);

$entity = $manager->getById(7);
hlBlockExpect($entity === $manager->getById(7), 'ORM-обёртка должна кешироваться в менеджере.');
hlBlockExpect($moduleLoads === 1, 'Модуль должен подключаться один раз на кешируемый HL-блок.');
hlBlockExpect($blockLookups === 1, 'Метаданные блока должны запрашиваться один раз.');

$rows = $entity->getList(['select' => ['ID', 'UF_XML_ID']]);
hlBlockExpect(count($rows) === 1 && $rows[0]['UF_XML_ID'] === 'first', 'getList() должен возвращать строки ORM.');
hlBlockExpect(
    $entity->getById(1, ['ID', 'UF_NAME'])['UF_NAME'] === 'Первая запись',
    'getById() должен возвращать найденную запись.'
);
hlBlockExpect($entity->getById(999) === null, 'Для отсутствующей записи getById() должен возвращать null.');

$newId = $entity->add(['UF_XML_ID' => 'second', 'UF_NAME' => 'Вторая запись']);
hlBlockExpect($newId === 2, 'add() должен возвращать ID созданной записи.');
hlBlockExpect($entity->update($newId, ['UF_NAME' => 'Обновлённая запись']), 'update() должен подтверждать успешное обновление.');
hlBlockExpect(
    $entity->getById($newId)['UF_NAME'] === 'Обновлённая запись',
    'После update() должны возвращаться обновлённые данные.'
);
hlBlockExpect($entity->delete($newId), 'delete() должен подтверждать успешное удаление.');
hlBlockExpect($entity->getById($newId) === null, 'Удалённая запись не должна находиться.');

$invalidIdFailed = false;
try {
    $manager->getById(0);
} catch (InvalidArgumentException) {
    $invalidIdFailed = true;
}
hlBlockExpect($invalidIdFailed, 'Нулевой ID HL-блока должен отклоняться.');

$missingBlockFailed = false;
try {
    $manager->getById(99);
} catch (RuntimeException $exception) {
    $missingBlockFailed = str_contains($exception->getMessage(), 'не найден');
}
hlBlockExpect($missingBlockFailed, 'Для отсутствующего HL-блока должно выбрасываться понятное исключение.');

$moduleFailureFailed = false;
try {
    (new \Project\Bitrix\HLBlock\HlBlockManager(
        static fn (): bool => false,
        static fn (): array => ['ID' => 7],
        static fn (): string => FakeHlBlockDataClass::class
    ))->getById(7);
} catch (RuntimeException $exception) {
    $moduleFailureFailed = str_contains($exception->getMessage(), 'highloadblock');
}
hlBlockExpect($moduleFailureFailed, 'Ошибка подключения модуля должна обрабатываться явно.');

$operationFailureFailed = false;
try {
    $entity->update(999, ['UF_NAME' => 'Не существует']);
} catch (RuntimeException $exception) {
    $operationFailureFailed = str_contains($exception->getMessage(), 'Запись не найдена');
}
hlBlockExpect($operationFailureFailed, 'Ошибки D7 Result должны попадать в исключение.');

echo "Проверки HLBlock пройдены.\n";
