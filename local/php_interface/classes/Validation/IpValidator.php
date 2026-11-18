<?php

declare(strict_types=1);

namespace Project\Validation;

class IpValidator extends Validator
{
    protected function defaultMessage(): string
    {
        return 'Неверный формат IP-адреса';
    }

    public function validate(): bool
    {
        $value = $this->stringValue();

        if ($value === null) {
            return $this->fail();
        }

        $flags = 0;
        if (!empty($this->options['ipv4'])) {
            $flags |= FILTER_FLAG_IPV4;
        }
        if (!empty($this->options['ipv6'])) {
            $flags |= FILTER_FLAG_IPV6;
        }

        return filter_var(trim($value), FILTER_VALIDATE_IP, $flags) !== false
            ? true
            : $this->fail();
    }
}
