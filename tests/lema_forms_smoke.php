<?php

declare(strict_types=1);

require_once __DIR__ . '/../local/php_interface/classes/Validation/FormValidator.php';
require_once __DIR__ . '/../local/php_interface/classes/Validation/LegacyFieldValidator.php';
require_once __DIR__ . '/../local/php_interface/classes/Validation/AjaxFormActions.php';
require_once __DIR__ . '/../local/php_interface/classes/Validation/AjaxForm.php';

$legacyRoot = __DIR__ . '/../local/php_interface/legacy/Lema/Forms/';
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
formsExpect(is_array($invalidForm->getErrors()['title']), 'showAllErrors=false must keep the first error only.');

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
$legacy = new \Lema\Forms\Form(
    [
        ['email', 'email'],
        ['enabled', 'boolean'],
        ['amount', 'numerical'],
    ],
    ['email' => 'person@example.org', 'enabled' => false, 'amount' => '12.75']
);
formsExpect($legacy->validate(), 'Legacy Form must preserve old rule names and accept boolean false and decimal numeric values.');
formsExpect($legacy->validators()['email'] === \Lema\Forms\Validators\EmailValidator::class, 'Legacy validator registry must return old class names.');

$directValidator = new \Lema\Forms\Validators\LengthValidator('title', 'Привет', ['min' => 3, 'max' => 8]);
formsExpect($directValidator->validate(), 'Legacy validator constructors and validate() method must remain available.');

$legacyAjax = new \Lema\Forms\AjaxForm();
formsExpect($legacyAjax instanceof \Lema\Forms\Form, 'Legacy AjaxForm must remain a subclass of legacy Form.');
formsExpect(!$legacyAjax->addRecord(0, []), 'AjaxForm must reject an empty iblock ID without requiring Bitrix.');
formsExpect(isset($legacyAjax->getErrors()['empty_iblock']), 'AjaxForm must retain the legacy error key.');

echo "Проверки Forms/Validators пройдены.\n";
