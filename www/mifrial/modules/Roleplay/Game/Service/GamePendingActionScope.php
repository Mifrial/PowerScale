<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Область хвоста следующего действия: профиль и номер попадания.
 */
final class GamePendingActionScope
{
    /**
     * Хвост накрывает попадание профиля.
     *
     * @param mixed $scope Сырая область эффекта.
     * @param string $profileType Тип профиля.
     * @param int $hitNumber Номер попадания.
     *
     * @return bool true, если накрывает.
     */
    public static function applies(mixed $scope, string $profileType, int $hitNumber): bool
    {
        $scope = is_array($scope) ? $scope : [];
        $components = is_array($scope['components'] ?? null) ? $scope['components'] : [];
        if (!in_array($profileType, $components, true)) {
            return false;
        }

        $hitCount = $scope['hit_count'] ?? 1;

        return $hitCount === 'all' || (is_int($hitCount) && $hitNumber <= $hitCount);
    }
}
