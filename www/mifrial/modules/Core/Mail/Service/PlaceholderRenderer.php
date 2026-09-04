<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Service;

use Mifrial\Core\Mail\Exception\MailException;

/**
 * Подстановка `{{ident}}` / `{{ident@default:'…'}}`.
 */
final class PlaceholderRenderer
{
    private const TOKEN_PATTERN = "/\\{\\{([A-Za-z_][A-Za-z0-9_]*)(?:@default:'([^']*)')?\\}\\}/";

    /**
     * Подставляет payload в шаблон.
     *
     * @param string $template Текст с плейсхолдерами.
     * @param array<string, string> $payload Значения.
     *
     * @return string Результат.
     *
     * @throws MailException Если остался битый `{{`.
     */
    public function render(string $template, array $payload): string
    {
        $replaced = preg_replace_callback(
            self::TOKEN_PATTERN,
            function (array $match) use ($payload): string {
                return $this->tokenValue($match, $payload);
            },
            $template,
        );
        if (!is_string($replaced) || str_contains($replaced, '{{')) {
            throw new MailException('MAIL_INVALID', 'Mail placeholder is invalid');
        }

        return $replaced;
    }

    /**
     * Значение одного токена.
     *
     * @param array<int, string> $match Совпадение.
     * @param array<string, string> $payload Поля.
     *
     * @return string Подстановка.
     */
    private function tokenValue(array $match, array $payload): string
    {
        $name = $match[1];
        $raw = $payload[$name] ?? '';
        if ($raw !== '') {
            return $raw;
        }

        if (str_contains($match[0], '@default:')) {
            return $match[2] ?? '';
        }

        return '';
    }
}
