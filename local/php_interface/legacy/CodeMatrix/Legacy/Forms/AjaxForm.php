<?php

/**
 * Совместимый AJAX-класс формы из lema.lib.
 * Подключать после адаптера Form и Composer autoload.
 */

namespace CodeMatrix\Legacy\Forms;

class AjaxForm extends Form
{
    use \Project\Validation\AjaxFormActions;
}
