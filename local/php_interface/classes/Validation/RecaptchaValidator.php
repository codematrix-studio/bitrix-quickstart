<?php

declare(strict_types=1);

namespace Project\Validation;

class RecaptchaValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Неверный проверочный код';
    }

    public function validate(): bool
    {
        $response = $this->stringValue();
        $verify = $this->options['verify'] ?? $this->options['verifier'] ?? null;

        if ($response === null || trim($response) === '' || !is_callable($verify)) {
            // Нет токена или серверной проверки — всегда отказ, без аварийного завершения формы.
            return $this->fail();
        }

        $remoteIp = $this->options['remoteIp'] ?? null;

        try {
            $verified = $verify($response, $remoteIp, $this->options);
        } catch (\Throwable $exception) {
            // Сетевые ошибки и исключения провайдера не должны разрешать отправку формы.
            return $this->fail();
        }

        return $verified === true ? true : $this->fail();
    }
}
