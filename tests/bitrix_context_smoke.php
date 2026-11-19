<?php

declare(strict_types=1);

namespace Bitrix\Main {

final class Application
{
    private static ?self $instance = null;

    public function __construct(private $context)
    {
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function getContext()
    {
        return $this->context;
    }
}
}

namespace {

require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Context/PerClassBitrixInstance.php';
require_once __DIR__ . '/../local/php_interface/classes/Support/PagePathMatcher.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Context/Context.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Context/Request.php';
require_once __DIR__ . '/../local/php_interface/classes/Bitrix/Context/Page.php';

use Bitrix\Main\Application;
use Project\Bitrix\Context\Context;
use Project\Bitrix\Context\Page;
use Project\Bitrix\Context\Request;

function contextExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class FakeBitrixRequest
{
    public function __construct(private string $directory)
    {
    }

    public function getRequestedPageDirectory(): string
    {
        return $this->directory;
    }
}

final class FakeBitrixContext
{
    public function __construct(private $request)
    {
    }

    public function getRequest()
    {
        return $this->request;
    }
}

// Отсутствующее приложение должно приводить к понятному исключению.
Application::setInstance(null);
Context::resetInstance();
Request::resetInstance();

$applicationMissing = false;
try {
    Context::get();
} catch (RuntimeException $exception) {
    $applicationMissing = str_contains($exception->getMessage(), 'Bitrix');
}
contextExpect($applicationMissing, 'Без Bitrix Application контекст должен завершаться RuntimeException.');

// Контекст и запрос должны лениво получать и кешировать объекты Bitrix.
$request = new FakeBitrixRequest('/catalog/item/');
$bitrixContext = new FakeBitrixContext($request);
Application::setInstance(new Application($bitrixContext));

Context::resetInstance();
Request::resetInstance();

contextExpect(Context::get() === $bitrixContext, 'Context должен возвращать объект текущего Bitrix-контекста.');
contextExpect(Context::get() === $bitrixContext, 'Context должен кешировать объект в пределах запроса.');
contextExpect(Request::get() === $request, 'Request должен возвращать HTTP-запрос из контекста.');
contextExpect(Request::get() === $request, 'Request должен кешировать HTTP-запрос.');
contextExpect(Page::inDirectory('/catalog/'), 'Page должен определять текущий каталог и вложенные страницы.');
contextExpect(Page::inRootDirectory('catalog'), 'Page должен определять корневой раздел.');
contextExpect(!Page::inRootDirectory('item'), 'Page не должен принимать вложенный сегмент за корневой раздел.');

// После сброса фасадов должен использоваться новый контекст (важно для тестов и долгоживущих процессов).
$newRequest = new FakeBitrixRequest('/news/article/');
$newContext = new FakeBitrixContext($newRequest);
Application::setInstance(new Application($newContext));

Request::resetInstance();
Context::resetInstance();

contextExpect(Context::get() === $newContext, 'После resetInstance() Context должен получить новый контекст.');
contextExpect(Request::get() === $newRequest, 'После resetInstance() Request должен получить новый запрос.');
contextExpect(Page::inDirectory('/news/'), 'Page должен работать с новым запросом после сброса кеша.');

// Некорректный результат getContext() должен отклоняться на границе фасада.
Application::setInstance(new Application(null));
Context::resetInstance();
Request::resetInstance();

$invalidContextFailed = false;
try {
    Context::get();
} catch (RuntimeException $exception) {
    $invalidContextFailed = str_contains($exception->getMessage(), 'контекст');
}
contextExpect($invalidContextFailed, 'Пустой результат getContext() должен приводить к RuntimeException.');

// Некорректный результат getRequest() также должен отклоняться.
Application::setInstance(new Application(new FakeBitrixContext(null)));
Context::resetInstance();
Request::resetInstance();

$invalidRequestFailed = false;
try {
    Request::get();
} catch (RuntimeException $exception) {
    $invalidRequestFailed = str_contains($exception->getMessage(), 'HTTP-запрос');
}
contextExpect($invalidRequestFailed, 'Пустой результат getRequest() должен приводить к RuntimeException.');

echo "Проверки Bitrix Context пройдены.\n";
}
