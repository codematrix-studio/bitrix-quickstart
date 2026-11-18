<?php

declare(strict_types=1);

namespace Project\Validation;

use LogicException;

class RecaptchaValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Неверный проверочный код';
    }

    public function validate(): bool
    {
        $response = $this->stringValue();
        $verify = $this->options['verify'] ?? null;

        if ($response === null || trim($response) === '') {
            return $this->fail();
        }

        if (!is_callable($verify)) {
            throw new LogicException(
                'Для проверки reCAPTCHA необходимо передать callable verify. Секретный ключ нельзя заменять публичным sitekey.'
            );
        }

        $remoteIp = $this->options['remoteIp'] ?? null;
        $verified = $verify($response, $remoteIp, $this->options);

        return $verified === true ? true : $this->fail();
    }
}
