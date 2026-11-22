<?php
declare(strict_types=1);

namespace Bitrix\Main {
    class Loader
    {
        public static function includeModule(string $name): bool
        {
            return in_array($name, ['sale', 'iblock'], true);
        }
    }
}

namespace Bitrix\Sale {
    class Order
    {
        public static array $orders = [];

        public static function load(int $id)
        {
            return self::$orders[$id] ?? null;
        }

        public static function loadByAccountNumber(string $number)
        {
            foreach (self::$orders as $order) {
                if ($order->getField('ACCOUNT_NUMBER') === $number) {
                    return $order;
                }
            }

            return null;
        }
    }
}

namespace Bitrix\Sale\Delivery\Services {
    class Manager
    {
        public static function getById(int $id): array
        {
            return ['NAME' => $id === 7 ? 'Курьер' : 'Пункт выдачи'];
        }
    }
}

namespace Bitrix\Iblock {
    class ElementTable
    {
        public static function getList(array $parameters): \FakeResult
        {
            return new \FakeResult([
                ['ID' => 501, 'IBLOCK_ELEMENT_IBLOCK_SECTION_NAME' => 'Категория A'],
                ['ID' => 502, 'IBLOCK_ELEMENT_IBLOCK_SECTION_NAME' => 'Категория B'],
            ]);
        }
    }
}

namespace {
    require_once __DIR__ . '/../local/php_interface/classes/Support/PerClassInstance.php';
    require_once __DIR__ . '/../local/php_interface/classes/Seo/Analytics/ScriptRenderer.php';
    require_once __DIR__ . '/../local/php_interface/classes/Seo/Analytics/GtmFormEvent.php';
    require_once __DIR__ . '/../local/php_interface/classes/Seo/Analytics/BitrixECommerce.php';
    require_once __DIR__ . '/../local/php_interface/legacy/CodeMatrix/Legacy/Seo/GTMFormSubmit.php';
    require_once __DIR__ . '/../local/php_interface/legacy/CodeMatrix/Legacy/Seo/ECommerce.php';

    function seoAnalyticsExpect(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    class FakeResult
    {
        private array $rows;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function fetch(): ?array
        {
            return array_shift($this->rows) ?: null;
        }
    }

    class FakePropertyCollection
    {
        public function getArray(): array
        {
            return ['properties' => [
                ['CODE' => 'EMAIL', 'VALUE' => ['buyer@example.com']],
                ['CODE' => 'PHONE', 'VALUE' => ['+79990000000']],
                ['CODE' => 'NAME', 'VALUE' => ['Покупатель']],
            ]];
        }
    }

    class FakePayment
    {
        public function getPaymentSystemName(): string
        {
            return 'Оплата картой';
        }
    }

    class FakeBasketItem
    {
        public function __construct(private int $id, private string $name, private float $price)
        {
        }

        public function getProductId(): int
        {
            return $this->id;
        }

        public function getField(string $name)
        {
            return $name === 'NAME' ? $this->name : null;
        }

        public function getPrice(): float
        {
            return $this->price;
        }

        public function getQuantity(): float
        {
            return 2.0;
        }
    }

    class FakeOrder
    {
        public function __construct(
            private int $id,
            private string $accountNumber,
            private int $productId,
            private string $productName,
            private float $price,
            private int $deliveryId
        ) {
        }

        public function getId(): int
        {
            return $this->id;
        }

        public function getPrice(): float
        {
            return $this->price;
        }

        public function getField(string $name)
        {
            return $name === 'ACCOUNT_NUMBER' ? $this->accountNumber : null;
        }

        public function getPropertyCollection(): FakePropertyCollection
        {
            return new FakePropertyCollection();
        }

        public function getPaymentCollection(): array
        {
            return [new FakePayment()];
        }

        public function getDeliverySystemId(): array
        {
            return [$this->deliveryId];
        }

        public function getBasket(): array
        {
            return [new FakeBasketItem($this->productId, $this->productName, $this->price)];
        }
    }

    \Bitrix\Sale\Order::$orders = [
        101 => new FakeOrder(101, 'A-101', 501, 'Товар A', 1200.0, 7),
        102 => new FakeOrder(102, 'A-102', 502, 'Товар B', 2400.0, 8),
    ];

    $event = \Project\Seo\Analytics\GtmFormEvent::get();
    $payload = $event->setEvent()
        ->setElementId('contact-form')
        ->setElementClasses('.contact_form js-form')
        ->setElements([
            'email' => 'customer@example.com',
            'phone' => '+79990000000',
            'password' => 'secret',
            'comment' => 'Личный комментарий',
        ])
        ->getResult();

    seoAnalyticsExpect($payload === [
        'event' => 'gtm.formSubmit',
        'gtm.elementClasses' => 'contact_form js-form',
        'formId' => 'contact-form',
    ], 'GTM должен сохранять безопасные метаданные формы и игнорировать значения полей.');

    $event->setElement('email', 'private@example.com');
    seoAnalyticsExpect(!array_key_exists('gtm.element.email.value', $event->getResult()), 'setElement() не должен сохранять значения полей.');

    $script = $event->jsonResult(true);
    seoAnalyticsExpect(str_contains($script, '<script>'), 'Должен формироваться script-тег.');
    seoAnalyticsExpect(!str_contains($script, 'private@example.com'), 'PII не должен попадать в script.');
    seoAnalyticsExpect($event->getResult() === ['event' => 'gtm.formSubmit'], 'После jsonResult() состояние должно сбрасываться.');

    $event->setElementId('form-before-event')->setEvent('custom.submit');
    seoAnalyticsExpect(($event->getResult()['formId'] ?? null) === 'form-before-event', 'setEvent() не должен стирать ранее заданные метаданные.');

    $attack = \Project\Seo\Analytics\ScriptRenderer::render(['value' => '</script><script>alert(1)</script>&"']);
    seoAnalyticsExpect(!str_contains($attack, '</script><script>'), 'JSON должен защищать HTML-контекст script.');
    seoAnalyticsExpect(str_contains($attack, '\\u003C'), 'Угловые скобки должны кодироваться.');

    $commerce = \Project\Seo\Analytics\BitrixECommerce::get();
    $first = $commerce->setIblockId(4)->load(101)->getGoogleCode();
    seoAnalyticsExpect($first['transactionId'] === 101, 'В Google payload должен быть ID первого заказа.');
    seoAnalyticsExpect($first['transactionProducts'][0]['category'] === 'Категория A', 'Для товара должна определяться категория.');
    seoAnalyticsExpect($first['delivery'] === 'Курьер', 'Должна определяться доставка.');

    $firstPayload = json_encode([$first, $commerce->getYandexCode()], JSON_UNESCAPED_UNICODE);
    foreach (['buyer@example.com', '+79990000000', 'Покупатель'] as $privateValue) {
        seoAnalyticsExpect(!str_contains($firstPayload, $privateValue), 'Персональные данные не должны попадать в ecommerce payload.');
    }

    $second = $commerce->loadByAccNumber('A-102')->getGoogleCode();
    seoAnalyticsExpect($second['transactionId'] === 102, 'При повторной загрузке должен использоваться новый заказ.');
    seoAnalyticsExpect($second['transactionProducts'][0]['sku'] === 502, 'Кеш товаров предыдущего заказа должен сбрасываться.');
    seoAnalyticsExpect($second['transactionProducts'][0]['category'] === 'Категория B', 'Кеш категорий предыдущего заказа должен сбрасываться.');
    seoAnalyticsExpect($second['transactionTotal'] === 2400.0, 'Сумма должна соответствовать новому заказу.');

    $legacy = \CodeMatrix\Legacy\Seo\GTMFormSubmit::get();
    $legacy->setElementId('legacy-form')->setElements(['name' => 'Имя', 'comment' => 'Текст']);
    seoAnalyticsExpect(!str_contains($legacy->jsonResult(true), 'Имя'), 'Legacy API не должен отправлять значения полей.');
    seoAnalyticsExpect(is_subclass_of(\CodeMatrix\Legacy\Seo\ECommerce::class, \Project\Seo\Analytics\BitrixECommerce::class), 'Legacy ECommerce должен быть адаптером.');

    echo "Проверки SEO-аналитики пройдены.\n";
}
