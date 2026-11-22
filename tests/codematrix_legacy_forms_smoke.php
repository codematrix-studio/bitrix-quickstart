<?php

declare(strict_types=1);

 
// Минимальный PSR-4 автозагрузчик для автономного запуска без vendor/autoload.php.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Project\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../local/php_interface/classes/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/../local/php_interface/classes/Validation/FormValidator.php';
require_once __DIR__ . '/../local/php_interface/classes/Validation/LegacyFieldValidator.php';
require_once __DIR__ . '/../local/php_interface/classes/Validation/AjaxFormActions.php';
require_once __DIR__ . '/../local/php_interface/classes/Validation/AjaxForm.php';

$legacyRoot = __DIR__ . '/../local/php_interface/legacy/CodeMatrix/Legacy/Forms/';
require_once $legacyRoot . 'Form.php';
require_once $legacyRoot . 'AjaxForm.php';

foreach ([
    'Validator.php',
    'BooleanValidator.php',
    'EmptyValidator.php',
    'EmailValidator.php',
    'IpValidator.php',
    'LengthValidator.php',
    'NumericValidator.php',
    'PhoneValidator.php',
    'RecaptchaValidator.php',
    'RegexValidator.php',
    'UrlValidator.php',
] as $file) {
    require_once $legacyRoot . 'Validators/' . $file;
}

function formsExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Современный API: исходный формат правил lema.lib сохранён.
$form = new Project\Validation\FormValidator(
    [
        ['email', 'email'],
        ['age', 'numeric'],
        ['active', 'boolean'],
        ['name', 'required'],
        ['name', 'length', ['min' => 2, 'max' => 20]],
    ],
    [
        'email' => 'dev@example.org',
        'age' => '18.5',
        'active' => false,
        'name' => ' Иван ',
    ]
);
formsExpect($form->validate(), 'FormValidator must accept a valid form and boolean false.');
formsExpect($form->getField('name') === 'Иван', 'String fields must be trimmed.');
formsExpect($form->getField('active') === false, 'Boolean false must not be converted to an empty string.');

// Numeric range options must be valid finite numbers and form a consistent interval.
$boundedNumeric = new Project\Validation\NumericValidator('amount', '10.5', ['min' => '10', 'max' => '11']);
formsExpect($boundedNumeric->validate(), 'NumericValidator must accept values inside a valid decimal range.');

foreach ([
    ['min' => 'abc'],
    ['max' => INF],
    ['min' => 10, 'max' => 5],
] as $invalidBounds) {
    $invalidBoundsRejected = false;

    try {
        (new Project\Validation\NumericValidator('amount', '10', $invalidBounds))->validate();
    } catch (InvalidArgumentException $exception) {
        $invalidBoundsRejected = true;
    }

    formsExpect($invalidBoundsRejected, 'NumericValidator must reject invalid or contradictory range options.');
}

// Length bounds describe character counts and therefore must be finite non-negative integers.
$validLengthBounds = new Project\Validation\LengthValidator('title', 'abc', ['min' => '2.0', 'max' => 3]);
formsExpect($validLengthBounds->validate(), 'LengthValidator must accept integral numeric bounds.');

foreach ([
    ['min' => 1.5],
    ['max' => 2.5],
    ['min' => INF],
    ['min' => -1],
    ['min' => 4, 'max' => 2],
] as $invalidLengthBounds) {
    $invalidLengthBoundsRejected = false;

    try {
        (new Project\Validation\LengthValidator('title', 'abc', $invalidLengthBounds))->validate();
    } catch (InvalidArgumentException $exception) {
        $invalidLengthBoundsRejected = true;
    }

    formsExpect($invalidLengthBoundsRejected, 'LengthValidator must reject fractional, non-finite, negative, or contradictory bounds.');
}

// required сохраняет старый смысл: false — неотмеченный флажок, а 0 — заданное значение.
$requiredBoolean = new Project\Validation\FormValidator(
    [['accepted', 'required'], ['quantity', 'required']],
    ['accepted' => false, 'quantity' => 0]
);
formsExpect(!$requiredBoolean->validate(), 'Required must reject false values.');
formsExpect(isset($requiredBoolean->getErrors()['accepted']), 'Required must report false as an empty value.');
formsExpect(!isset($requiredBoolean->getErrors()['quantity']), 'Required must accept numeric zero.');

// Arrays from POST must not trigger trim() type errors.
$arrayForm = new Project\Validation\FormValidator(
    [['choices', 'required']],
    ['choices' => ['first', 'second']]
);
formsExpect($arrayForm->validate(), 'Array-valued fields must be supported.');

// Required and length rules must treat whitespace as empty and count UTF-8 characters.
$invalidForm = new Project\Validation\FormValidator(
    [
        ['title', 'required'],
        ['title', 'length', ['min' => 2, 'max' => 4]],
    ],
    ['title' => '   ']
);
formsExpect(!$invalidForm->validate(), 'Whitespace-only required values must be rejected.');
formsExpect(is_string($invalidForm->getErrors()['title']), 'showAllErrors=false must keep the first error only.');

$allErrorsForm = new Project\Validation\FormValidator(
    [
        ['title', 'required', ['showAllErrors' => true]],
        ['title', 'length', ['min' => 2, 'max' => 4, 'showAllErrors' => true]],
    ],
    ['title' => ' ']
);
formsExpect(!$allErrorsForm->validate(), 'Invalid fields must fail validation.');
formsExpect(count((array) $allErrorsForm->getErrors()['title']) === 2, 'showAllErrors must collect errors from multiple rules.');

// Repeated validation recalculates errors instead of retaining stale failures.
$repeatForm = new Project\Validation\FormValidator([['email', 'email']], ['email' => 'bad']);
formsExpect(!$repeatForm->validate(), 'Invalid email must fail.');
$repeatForm->setFields(['email' => 'ok@example.org']);
formsExpect($repeatForm->validate() && $repeatForm->getErrors() === [], 'Corrected values must clear previous errors.');

// Legacy API and validator class names.
$legacy = new \CodeMatrix\Legacy\Forms\Form(
    [
        ['email', 'email'],
        ['enabled', 'boolean'],
        ['amount', 'numerical'],
    ],
    ['email' => 'person@example.org', 'enabled' => true, 'amount' => '12']
);
formsExpect($legacy->validate(), 'Legacy Form must preserve old rule names and accept true boolean and integer numeric values.');
$legacyDecimal = new \CodeMatrix\Legacy\Forms\Form([['amount', 'numerical']], ['amount' => '12.75']);
formsExpect(!$legacyDecimal->validate(), 'Legacy numerical validator must reject decimal values as the original Lema validator did.');
$legacyZero = new \CodeMatrix\Legacy\Forms\Form([['amount', 'numerical']], ['amount' => '0']);
formsExpect($legacyZero->validate(), 'Legacy numerical validator must accept integer zero without the old truthiness bug.');
formsExpect($legacy->validators()['email'] === \CodeMatrix\Legacy\Forms\Validators\EmailValidator::class, 'Legacy validator registry must return old class names.');
$legacyFalseBoolean = new \CodeMatrix\Legacy\Forms\Form([['enabled', 'boolean']], ['enabled' => false]);
formsExpect(!$legacyFalseBoolean->validate(), 'Legacy boolean validator must retain the original true-only filter behavior.');

$directValidator = new \CodeMatrix\Legacy\Forms\Validators\LengthValidator('title', 'Привет', ['min' => 3, 'max' => 8]);
formsExpect($directValidator->validate(), 'Legacy validator constructors and validate() method must remain available.');

$legacyAjax = new \CodeMatrix\Legacy\Forms\AjaxForm();
formsExpect($legacyAjax instanceof \CodeMatrix\Legacy\Forms\Form, 'Legacy AjaxForm must remain a subclass of legacy Form.');
formsExpect(!$legacyAjax->addRecord(0, []), 'AjaxForm must reject an empty iblock ID without requiring Bitrix.');
formsExpect(isset($legacyAjax->getErrors()['empty_iblock']), 'AjaxForm must retain the legacy error key.');

echo "Проверки Forms/Validators пройдены.\n";


// Реестр современного валидатора должен ссылаться на реальные классы.
formsExpect(
    $form->validators()['email'] === Project\Validation\EmailValidator::class,
    'Modern validator registry must expose concrete validator classes.'
);

// reCAPTCHA без серверного провайдера закрывается отказом, но не роняет форму.
$captchaForm = new Project\Validation\FormValidator(
    [['captcha', 'recaptcha']],
    ['captcha' => 'token']
);
formsExpect(!$captchaForm->validate(), 'reCAPTCHA must fail closed when no verifier is configured.');

$captchaForm->setRecaptchaVerifier(static fn (string $token): bool => $token === 'valid-token');
$captchaForm->setFields(['captcha' => 'valid-token']);
formsExpect($captchaForm->validate(), 'reCAPTCHA must use the explicitly injected verifier.');

// Старый пользовательский валидатор должен запускаться один раз на одно поле и правило.
class FormsCountedLegacyValidator extends \CodeMatrix\Legacy\Forms\Validators\Validator
{
    protected string $ruleName = 'required';
    public static int $calls = 0;

    public function validate(): bool
    {
        self::$calls++;
        $this->hasError = false;
        return true;
    }
}

class FormsCustomLegacyForm extends \CodeMatrix\Legacy\Forms\Form
{
    public function validators(): array
    {
        return array_replace(parent::validators(), [
            'custom' => FormsCountedLegacyValidator::class,
        ]);
    }
}

$customForm = new FormsCustomLegacyForm([['field', 'custom']], ['field' => 'value']);
formsExpect($customForm->validate(), 'Custom legacy validators must remain supported.');
formsExpect(FormsCountedLegacyValidator::$calls === 1, 'Legacy validator must not be executed twice by one validation pass.');

