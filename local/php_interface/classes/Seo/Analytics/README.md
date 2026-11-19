# Аналитика и dataLayer

## Безопасность данных

Классы аналитики не отправляют персональные данные покупателя или значения полей форм. Не передавайте в аналитику ФИО, email, телефон, адрес, комментарии заказа, пароли, содержимое textarea и произвольные значения POST.

## GTM: отправка события формы

Используйте \`Project\Seo\Analytics\GtmFormEvent\`:

\`\`\`php
use Project\Seo\Analytics\GtmFormEvent;

GtmFormEvent::get()
    ->setEvent('gtm.formSubmit')
    ->setElementId('contact-form')
    ->setElementClasses('contact-form')
    ->jsonResult();
\`\`\`

В dataLayer попадут только имя события, ID и CSS-классы формы. Значения полей не собираются. Старые методы \`setElement()\` и \`setElements()\` сохранены для совместимости вызовов, но намеренно игнорируют переданные значения. После \`jsonResult()\` состояние singleton сбрасывается.

## Электронная торговля Bitrix

\`\`\`php
use Project\Seo\Analytics\BitrixECommerce;

BitrixECommerce::get()
    ->setIblockId(2)
    ->loadByAccNumber($orderNumber)
    ->setViewContent();
\`\`\`

Формируются ecommerce-события для Google и Яндекс Метрики: ID и сумма заказа, доставка, платёжная система и список товаров. ФИО, email, телефон, адрес и комментарии заказа не включаются в payload.

Метод \`getOrderProps()\` сохранён для серверной совместимости и может возвращать персональные данные. Не передавайте его результат в аналитику.

Кеш заказа сбрасывается при каждой загрузке. Модуль \`sale\` подключается только при вызове \`load()\` или \`loadByAccNumber()\`, а не при подключении файла.

## JSON внутри script

\`ScriptRenderer\` использует \`JSON_HEX_TAG\`, \`JSON_HEX_AMP\`, \`JSON_HEX_APOS\`, \`JSON_HEX_QUOT\`, \`JSON_INVALID_UTF8_SUBSTITUTE\` и \`JSON_THROW_ON_ERROR\`. Это защищает контекст HTML script от внедрения закрывающего тега из данных.

## Совместимость

После отключения старого \`lema.lib\` и подключения Composer autoload можно явно подключить адаптеры:

- \`legacy/Lema/Seo/ECommerce.php\`;
- \`legacy/Lema/Seo/GTMFormSubmit.php\`.

Сохранены имена классов и основные публичные методы. Намеренное изменение: PII и значения полей формы больше не передаются в dataLayer. Реальную интеграцию с ядром Bitrix и модулем Sale необходимо проверить на установленном ядре.
