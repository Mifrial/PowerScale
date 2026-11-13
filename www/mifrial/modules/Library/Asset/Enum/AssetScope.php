<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Enum;

use Mifrial\Library\Asset\Exception\AssetInvalidException;

/**
 * Часть библиотеки: общая или личная.
 */
enum AssetScope: string
{
    case Shared = 'shared';
    case Personal = 'personal';

    /**
     * Разбирает значение из JSON действия.
     *
     * @param string $scope Строка `shared` или `personal`.
     *
     * @return self Часть библиотеки.
     *
     * @throws AssetInvalidException Если значение неизвестно.
     */
    public static function fromInput(string $scope): self
    {
        $parsedScope = self::tryFrom(trim($scope));
        if (!$parsedScope instanceof self) {
            throw new AssetInvalidException('Asset scope is invalid');
        }

        return $parsedScope;
    }
}
