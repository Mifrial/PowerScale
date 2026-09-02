<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Dto;

/**
 * Исходящая cookie для очереди Set-Cookie.
 */
final class OutgoingCookie
{
    /**
     * Создаёт описание cookie Path=/ SameSite=Lax.
     *
     * @param string $name Имя.
     * @param string $value Значение.
     * @param bool $httpOnly Флаг HttpOnly.
     * @param bool $secure Флаг Secure.
     * @param int $maxAge Секунды жизни; 0 — сразу истекает.
     *
     * @return void
     */
    public function __construct(
        private readonly string $name,
        private readonly string $value,
        private readonly bool $httpOnly,
        private readonly bool $secure,
        private readonly int $maxAge,
    ) {
    }

    /**
     * Имя cookie.
     *
     * @return string Имя.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Значение cookie.
     *
     * @return string Значение.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Собирает строку заголовка Set-Cookie без имени заголовка.
     *
     * @return string Атрибуты cookie.
     */
    public function headerLine(): string
    {
        $parts = [
            $this->name . '=' . rawurlencode($this->value),
            'Path=/',
            'Max-Age=' . $this->maxAge,
            'SameSite=Lax',
        ];
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }
}
