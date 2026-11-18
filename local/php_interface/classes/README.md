## Highload-блоки

Для работы с Highload-блоками используйте `Project\Bitrix\HLBlock\HlBlockManager` и `HlBlockEntity`. Менеджер сохраняет прежний конструктор с тремя callable-зависимостями, а получение метаданных и компиляция ORM-класса вынесены во внутренний `HlBlockRepository`. Результаты публичных методов не изменены: список возвращается массивом, добавление — ID, обновление и удаление — `true` при успехе. Для блоков с полем `UF_XML_ID` доступен дополнительный метод `findByXmlId()`. Подробности: [Bitrix/HLBlock/README.md](Bitrix/HLBlock/README.md).
