<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Объект видимости NPC и вырезание секций из листа.
 */
final class GameNpcVisibility
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HIDDEN = [
        'characteristics' => [
            'choices.characteristicPurchases',
            'sheet.characteristicPurchases',
            'sheet.characteristicPurchaseOs',
            'sheet.osSurchargeTotal',
        ],
        'abilities' => [
            'choices.abilities',
            'sheet.abilityLevels',
            'sheet.racialAbilityCodes',
            'sheet.coveredPaths',
        ],
        'inventory' => [
            'choices.inventory',
            'sheet.equippedModifiers',
        ],
        'resources' => [
            'choices.money',
            'sheet.money',
            'sheet.resources',
        ],
    ];

    /**
     * Проверяет объект видимости.
     *
     * @param mixed $visibility JSON.
     *
     * @return array{scope: string, userIds: array<int, int>, sections: array<int, string>} Объект.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function normalize(mixed $visibility): array
    {
        if (!is_array($visibility)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: visibility');
        }

        $scope = $visibility['scope'] ?? null;
        if (!is_string($scope) || !in_array($scope, ['all', 'gm', 'users'], true)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: visibility.scope');
        }

        return [
            'scope' => $scope,
            'userIds' => $this->userIds($visibility['userIds'] ?? null, $scope),
            'sections' => $this->sections($visibility['sections'] ?? null),
        ];
    }

    /**
     * Снимает скрытые ключи. Полный лист не трогает.
     *
     * @param array<string, mixed> $version Лист.
     * @param array<string, mixed> $visibility Объект.
     * @param bool $fullSheet Ведущий видит всё.
     *
     * @return array<string, mixed> Лист ответа.
     */
    public function mask(array $version, array $visibility, bool $fullSheet): array
    {
        if ($fullSheet) {
            return $version;
        }

        $open = is_array($visibility['sections'] ?? null) ? $visibility['sections'] : [];
        foreach (self::HIDDEN as $section => $paths) {
            if (in_array($section, $open, true)) {
                continue;
            }

            $version = $this->hide($version, $paths);
        }

        return $version;
    }

    /**
     * Id пользователей. Пустой список, если scope не users.
     *
     * @param mixed $userIds JSON.
     * @param string $scope Scope.
     *
     * @return array<int, int> Id.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function userIds(mixed $userIds, string $scope): array
    {
        if ($scope !== 'users') {
            return [];
        }

        if (!is_array($userIds)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: visibility.userIds');
        }

        $ids = [];
        foreach ($userIds as $userId) {
            if (!is_int($userId)) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: visibility.userIds');
            }

            $ids[] = $userId;
        }

        return $ids;
    }

    /**
     * Четыре кода секций.
     *
     * @param mixed $sections JSON.
     *
     * @return array<int, string> Коды.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function sections(mixed $sections): array
    {
        if (!is_array($sections)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: visibility.sections');
        }

        $codes = [];
        foreach ($sections as $section) {
            if (!is_string($section) || !array_key_exists($section, self::HIDDEN)) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: visibility.sections');
            }

            $codes[] = $section;
        }

        return $codes;
    }

    /**
     * Снимает пути choices.* и sheet.*.
     *
     * @param array<string, mixed> $version Лист.
     * @param array<int, string> $paths Пути.
     *
     * @return array<string, mixed> Лист.
     */
    private function hide(array $version, array $paths): array
    {
        foreach ($paths as $path) {
            [$bag, $key] = explode('.', $path, 2);
            if (!is_array($version[$bag] ?? null)) {
                continue;
            }

            unset($version[$bag][$key]);
        }

        return $version;
    }
}
