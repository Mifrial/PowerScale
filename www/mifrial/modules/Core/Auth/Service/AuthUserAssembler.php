<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Interface\Service\IUserGroups;

/**
 * JSON текущего пользователя для фронтового User.
 */
final class AuthUserAssembler
{
    /**
     * Создаёт сборщик.
     *
     * @param IUserGroups $userGroups Группы.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserGroups $userGroups,
    ) {
    }

    /**
     * Собирает объект User.
     *
     * @param UserRecord $userRecord Профиль.
     * @param DateTime|null $lastLogin Момент last_used_at.
     *
     * @return array<string, mixed> JSON-поля.
     */
    public function view(UserRecord $userRecord, ?DateTime $lastLogin): array
    {
        $values = $userRecord->values();
        $userId = (int) $values['id'];
        $view = [
            'id' => $userId,
            'name' => $values['name'],
            'login' => $values['login'],
            'email' => is_string($values['email']) ? $values['email'] : '',
            'groups' => $this->groupNames($userId),
            'registered' => $this->unixOrNull($values['registered_at']),
            'active' => $values['active'] === true,
            'super_admin' => $this->userGroups->hasBypass($userId),
            'permissions' => $this->userGroups->permissionKeys($userId),
            'deactivated_until' => $this->unixOrNull($values['deactivated_until']),
            'deactivate_reason' => $values['deactivate_reason'],
        ];

        return $this->withOptionalProfile($view, $values, $lastLogin);
    }

    /**
     * Добавляет необязательные поля профиля.
     *
     * @param array<string, mixed> $view База.
     * @param array<string, mixed> $values Профиль.
     * @param DateTime|null $lastLogin last_used_at.
     *
     * @return array<string, mixed> JSON.
     */
    private function withOptionalProfile(array $view, array $values, ?DateTime $lastLogin): array
    {
        if (is_string($values['surname']) && $values['surname'] !== '') {
            $view['surname'] = $values['surname'];
        }

        if (is_string($values['nickname']) && $values['nickname'] !== '') {
            $view['nickname'] = $values['nickname'];
        }

        if ($lastLogin instanceof DateTime) {
            $view['lastLogin'] = $lastLogin->toUnix();
        }

        return $view;
    }

    /**
     * Имена групп пользователя.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, string> Имена.
     */
    private function groupNames(int $userId): array
    {
        $groupNames = [];
        foreach ($this->userGroups->groupsOfUser($userId) as $groupId) {
            $groupNames[] = (string) $this->userGroups->getById($groupId)->values()['name'];
        }

        return $groupNames;
    }

    /**
     * Unix из DateTime или null.
     *
     * @param mixed $moment Значение поля.
     *
     * @return int|null Секунды.
     */
    private function unixOrNull(mixed $moment): ?int
    {
        if ($moment instanceof DateTime) {
            return $moment->toUnix();
        }

        return null;
    }
}
