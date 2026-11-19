<?php
declare(strict_types=1);

namespace Project\Seo\Analytics;

use InvalidArgumentException;
use Project\Support\PerClassInstance;

/**
 * Событие формы без сбора значений полей.
 */
class GtmFormEvent extends PerClassInstance
{
    protected string $event = 'gtm.formSubmit';
    protected ?string $elementClasses = null;
    protected ?string $elementId = null;

    public function setEvent($event = 'gtm.formSubmit'): static
    {
        $event = is_scalar($event) ? trim((string) $event) : '';

        if ($event === '' || strlen($event) > 100 || preg_match('/^[a-zA-Z0-9_.:-]+$/D', $event) !== 1) {
            throw new InvalidArgumentException('Недопустимое имя события GTM.');
        }

        $this->event = $event;
        return $this;
    }

    public function setElementClasses($classes): static
    {
        if (!is_scalar($classes)) {
            $this->elementClasses = null;
            return $this;
        }

        $classes = trim((string) $classes);
        $classes = preg_replace('/(?:^|\\s)\\./', ' ', $classes) ?? $classes;
        $tokens = preg_split('/\\s+/', $classes, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_filter($tokens ?: [], static fn (string $token): bool =>
            preg_match('/^[a-zA-Z0-9_-]+$/D', $token) === 1
        );
        $normalized = implode(' ', array_slice($tokens, 0, 20));
        $this->elementClasses = $normalized === '' ? null : substr($normalized, 0, 300);
        return $this;
    }

    public function setElementId($id): static
    {
        if (!is_scalar($id)) {
            $this->elementId = null;
            return $this;
        }

        $id = trim((string) $id);
        $this->elementId = $id !== '' && strlen($id) <= 150
            && preg_match('/^[a-zA-Z0-9_.:-]+$/D', $id) === 1 ? $id : null;
        return $this;
    }

    /** Устаревший метод. Значение поля намеренно не сохраняется. */
    public function setElement($index, $value): static
    {
        return $this;
    }

    /** Устаревший метод. Значения полей намеренно игнорируются. */
    public function setElements(array $data = []): static
    {
        return $this;
    }

    public function getResult($inJson = false): array|string
    {
        $result = ['event' => $this->event];

        if ($this->elementClasses !== null) {
            $result['gtm.elementClasses'] = $this->elementClasses;
        }
        if ($this->elementId !== null) {
            $result['formId'] = $this->elementId;
        }

        return $inJson
            ? json_encode($result, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
                | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)
            : $result;
    }

    public function jsonResult($return = false, $jsonOptions = JSON_UNESCAPED_UNICODE)
    {
        // $jsonOptions оставлен для совместимости; защитные флаги всегда включены.
        $script = ScriptRenderer::render($this->getResult());
        $this->resetState();

        if ($return) {
            return $script;
        }
        echo $script;
    }

    public function resetState(): static
    {
        $this->event = 'gtm.formSubmit';
        $this->elementClasses = null;
        $this->elementId = null;
        return $this;
    }

}
