<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Dto;

/**
 * Срез `mail` из local.php.
 */
final class MailSettings
{
    /**
     * Собирает настройки.
     *
     * @param bool $flushInline Сразу flushJob после trigger.
     *
     * @return void
     */
    private function __construct(
        private readonly bool $flushInline,
    ) {
    }

    /**
     * Разбирает срез; нет ключа — inline выкл.
     *
     * @param mixed $section Значение mail.
     *
     * @return self Настройки.
     */
    public static function fromSection(mixed $section): self
    {
        if ($section === null) {
            $section = [];
        }

        if (!is_array($section)) {
            return new self(false);
        }

        return new self(($section['flush_inline'] ?? false) === true);
    }

    /**
     * Flush в том же запросе, что trigger.
     *
     * @return bool true, если inline.
     */
    public function flushInline(): bool
    {
        return $this->flushInline;
    }
}
