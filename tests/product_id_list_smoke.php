<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Support/ProductIdList.php';

use Project\Support\ProductIdList;

function productIdListExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$list = ProductIdList::fromValues([12, '7', 12, 0, -1, 'abc', ' 9 ', 1.5, true, null]);
productIdListExpect($list->toArray() === [12, 7, 9], 'Список должен содержать только уникальные положительные целые ID.');
productIdListExpect($list->count() === 3, 'Количество элементов списка рассчитано неверно.');
productIdListExpect($list->contains(7), 'Список должен находить существующий ID.');
productIdListExpect(!$list->contains(0), 'Нулевой ID не должен считаться существующим.');

$added = $list->add(15);
productIdListExpect($added->toArray() === [12, 7, 9, 15], 'Добавление должно возвращать новый список.');
productIdListExpect($list->toArray() === [12, 7, 9], 'Исходный список не должен изменяться.');
productIdListExpect($added->add(15) === $added, 'Повторное добавление существующего ID не должно менять список.');

$removed = $added->remove(7);
productIdListExpect($removed->toArray() === [12, 9, 15], 'Удаление должно убрать только указанный ID.');
productIdListExpect($removed->remove(999) === $removed, 'Удаление отсутствующего ID не должно менять список.');

$json = $list->toJson();
productIdListExpect(ProductIdList::fromStorageValue($json)->toArray() === [12, 7, 9], 'JSON должен корректно восстанавливаться.');
productIdListExpect(ProductIdList::fromStorageValue(serialize([12, '7', 12]))->toArray() === [12, 7], 'Старый сериализованный массив должен читаться для миграции.');
productIdListExpect(ProductIdList::fromStorageValue([5, '6', 0])->toArray() === [5, 6], 'Массив пользовательского поля должен нормализоваться.');
productIdListExpect(ProductIdList::fromStorageValue('not a list')->toArray() === [], 'Повреждённое значение должно давать пустой список.');
productIdListExpect(ProductIdList::fromStorageValue(str_repeat('x', 65537))->toArray() === [], 'Слишком большое значение cookie должно отклоняться.');

echo "Проверки ProductIdList пройдены.\n";
