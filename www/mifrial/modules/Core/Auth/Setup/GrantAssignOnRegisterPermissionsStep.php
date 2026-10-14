<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Setup;

use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\User\Dto\GroupPatch;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Interface\Service\IUserGroups;

/**
 * Дописывает create-ключи простым пользователям (группы assign_on_register).
 */
final class GrantAssignOnRegisterPermissionsStep implements ISetupStep
{
    /**
     * @var array<int, string>
     */
    private const KEYS = ['user.view', 'character.create', 'game.create'];

    /**
     * Создаёт шаг.
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
     * Возвращает id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Core/Auth:seed.assign-on-register-create-keys';
    }

    /**
     * Дописывает недостающие ключи, не снимая уже выданные.
     *
     * @return void
     *
     * @throws SetupException Если patch недопустим.
     */
    public function run(): void
    {
        foreach ($this->userGroups->getAssignOnRegisterIds() as $groupId) {
            $group = $this->userGroups->getById($groupId);
            $missing = array_values(array_diff(self::KEYS, $group->getPermissionKeys()));
            if ($missing === []) {
                continue;
            }

            try {
                $this->userGroups->update($groupId, GroupPatch::fromNormalized([
                    'permissions' => array_values(array_merge($group->getPermissionKeys(), $missing)),
                ]));
            } catch (UserInvalidException $exception) {
                throw new SetupException('SETUP_INVALID', 'Cannot update seed group', $exception);
            }
        }
    }
}
