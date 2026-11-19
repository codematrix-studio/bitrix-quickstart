<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/HLBlock/HlBlockRepository.php';
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
        $rows = array_values(self::$rows);
        $filter = $parameters['filter']['=UF_XML_ID'] ?? null;

        if (is_string($filter)) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($row['UF_XML_ID'] ?? null) === $filter
            ));
        }

        if (isset($parameters['limit'])) {
            $rows = array_slice($rows, 0, (int) $parameters['limit']);
        }

        return new FakeHlBlockQueryResult($rows);
    }

    public static function getByPrimary(int $id, array $parameters): FakeHlBlockQueryResult
    {
        return new FakeHlBlockQueryResult(isset(self::$rows[$id]) ? [self::$rows[$id]] : []);
    }

    public static function add(array $fields): FakeHlBlockOperationResult
    {
        if (($fields['UF_FAIL_ADD'] ?? false) === true) {
            return new FakeHlBlockOperationResult(false, 0, ['Тестовая ошибка добавления']);
        }

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
        if (!isset(self::$rows[$id])) {
            return new FakeHlBlockOperationResult(false, 0, ['Запись для удаления не найдена']);
        }

        unset(self::$rows[$id]);

        return new FakeHlBlockOperationResult();
    }
}

$moduleLoads = 0;
$blockLookups = 0;
$entityCompilations = 0;
$manager = new \Project\Bitrix\HLBlock\HlBlockManager(
    static function () use (&$moduleLoads): bool {
        $moduleLoads++;

        return true;
    },
    static function (int $id) use (&$blockLookups): ?array {
        $blockLookups++;

        return in_array($id, [7, 8], true)
            ? ['ID' => $id, 'NAME' => 'Тестовый блок']
            : null;
    },
    static function (array $block) use (&$entityCompilations): string {
        $entityCompilations++;

        return FakeHlBlockDataClass::class;
    }
);

$entity = $manager->getById(7);
hlBlockExpect($entity === $manager->getById(7), 'ORM-обёртка должна кешироваться в менеджере.');
hlBlockExpect($moduleLoads === 1, 'Модуль должен подключаться один раз.');
hlBlockExpect($blockLookups === 1, 'Метаданные должны запрашиваться один раз для одного ID.');
hlBlockExpect($entityCompilations === 1, 'ORM-класс должен компилироваться один раз для одного ID.');

$manager->getById(8);
hlBlockExpect($moduleLoads === 1, 'Модуль не должен повторно подключаться для другого HL-блока.');
hlBlockExpect($blockLookups === 2, 'Для другого ID должны загружаться собственные метаданные.');
hlBlockExpect($entityCompilations === 2, 'Для другого ID должна компилироваться собственная сущность.');

$rows = $entity->getList(['select' => ['ID', 'UF_XML_ID']]);
hlBlockExpect(count($rows) === 1 && $rows[0]['UF_XML_ID'] === 'first', 'getList() должен возвращать массив записей.');
hlBlockExpect(
    $entity->getById(1, ['ID', 'UF_NAME'])['UF_NAME'] === 'Первая запись',
    'getById() должен возвращать найденную запись.'
);
hlBlockExpect($entity->getById(999) === null, 'Для отсутствующей записи getById() должен возвращать null.');
hlBlockExpect(
    $entity->findByXmlId('first')['ID'] === 1,
    'findByXmlId() должен находить запись по UF_XML_ID.'
);

$newId = $entity->add(['UF_XML_ID' => 'second', 'UF_NAME' => 'Вторая запись']);
hlBlockExpect($newId === 2, 'add() должен возвращать ID созданной записи.');
hlBlockExpect($entity->update($newId, ['UF_NAME' => 'Обновлённая запись']), 'update() должен возвращать true при успехе.');
hlBlockExpect(
    $entity->getById($newId)['UF_NAME'] === 'Обновлённая запись',
    'После update() должны возвращаться обновлённые данные.'
);
hlBlockExpect($entity->delete($newId), 'delete() должен возвращать true при успехе.');
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

$addFailureFailed = false;
try {
    $entity->add(['UF_FAIL_ADD' => true]);
} catch (RuntimeException $exception) {
    $addFailureFailed = str_contains($exception->getMessage(), 'Тестовая ошибка добавления');
}
hlBlockExpect($addFailureFailed, 'Ошибки D7 Result при добавлении должны попадать в исключение.');

$deleteFailureFailed = false;
try {
    $entity->delete(999);
} catch (RuntimeException $exception) {
    $deleteFailureFailed = str_contains($exception->getMessage(), 'Запись для удаления не найдена');
}
hlBlockExpect($deleteFailureFailed, 'Ошибки D7 Result при удалении должны попадать в исключение.');

$emptyXmlIdFailed = false;
try {
    $entity->findByXmlId(' ');
} catch (InvalidArgumentException) {
    $emptyXmlIdFailed = true;
}
hlBlockExpect($emptyXmlIdFailed, 'Пустой UF_XML_ID должен отклоняться.');

echo "Проверки HLBlock пройдены.\n";
