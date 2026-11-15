<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;

/**
 * Формат стабильных кодов мира, раздела, статьи и типа ссылки.
 */
final class LoreCode
{
    private const CODE_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    private const LINK_TYPE_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }

    /**
     * Код соответствует шаблону.
     *
     * @param string $code Код.
     *
     * @return bool Флаг.
     */
    public static function isValid(string $code): bool
    {
        return preg_match(self::CODE_PATTERN, $code) === 1;
    }

    /**
     * Тип ссылки соответствует шаблону.
     *
     * @param string $type Тип.
     *
     * @return bool Флаг.
     */
    public static function isValidLinkType(string $type): bool
    {
        return preg_match(self::LINK_TYPE_PATTERN, $type) === 1;
    }

    /**
     * Требует допустимый код.
     *
     * @param string $code Код.
     * @param string $label Человеческое имя поля.
     *
     * @return string Код без изменений.
     *
     * @throws LoreInvalidException Если код недопустим.
     */
    public static function requireValid(string $code, string $label): string
    {
        if (!self::isValid($code)) {
            throw new LoreInvalidException($label . ' must match ^[a-z0-9][a-z0-9_-]{0,63}$');
        }

        return $code;
    }
}
