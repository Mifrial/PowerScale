<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Setup;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\User\Dto\GroupPatch;
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;

/**
 * Идемпотентные группы «Администраторы» / «Игрок» и оператор.
 */
final class BootstrapGroupsStep implements ISetupStep
{
    /**
     * Создаёт шаг.
     *
     * @param IUserAccounts $userAccounts Учётки.
     * @param IUserGroups $userGroups Группы.
     * @param UserIdentityRepository $identityRepository Identity.
     * @param AuthSettings $authSettings Срез auth.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccounts $userAccounts,
        private readonly IUserGroups $userGroups,
        private readonly UserIdentityRepository $identityRepository,
        private readonly AuthSettings $authSettings,
    ) {
    }

    /**
     * Возвращает id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Core/Auth:seed.bootstrap-groups';
    }

    /**
     * Создаёт группы и оператора, если их ещё нет.
     *
     * @return void
     *
     * @throws SetupException Если срез auth неполный или создать нельзя.
     */
    public function run(): void
    {
        $this->authSettings->assertOperatorComplete();
        $adminGroupId = $this->ensureGroup('Администраторы', true, false);
        $playerGroupId = $this->ensureGroup('Игрок', false, true);
        $operatorId = $this->ensureOperator();
        $this->ensureMember($operatorId, $adminGroupId);
        $this->ensureMember($operatorId, $playerGroupId);
        if ($this->identityRepository->findPassword($operatorId) === null) {
            $this->identityRepository->addPassword(
                $operatorId,
                password_hash($this->authSettings->operatorPassword(), PASSWORD_DEFAULT),
            );
        }
    }

    /**
     * Создаёт группу по имени, если нет.
     *
     * @param string $groupName Имя.
     * @param bool $bypass Флаг bypass.
     * @param bool $assignOnRegister Автовыдача при пустом groups.
     *
     * @return int Id группы.
     *
     * @throws SetupException Если создать группу нельзя.
     */
    private function ensureGroup(string $groupName, bool $bypass, bool $assignOnRegister): int
    {
        $existing = $this->userGroups->findByName($groupName);
        if ($existing !== null) {
            $this->ensureAssignOnRegister($existing->getId(), $existing->isAssignOnRegister(), $assignOnRegister);

            return $existing->getId();
        }

        try {
            return $this->userGroups->add(NewGroup::fromNormalized([
                'name' => $groupName,
                'active' => true,
                'bypass' => $bypass,
                'assign_on_register' => $assignOnRegister,
                'permissions' => [],
            ]));
        } catch (UserInvalidException $exception) {
            throw new SetupException('SETUP_INVALID', 'Cannot create seed group', $exception);
        }
    }

    /**
     * Дописывает флаг seed на уже существующую группу.
     *
     * @param int $groupId Id.
     * @param bool $currentAssign Текущий флаг.
     * @param bool $assignOnRegister Целевой флаг.
     *
     * @return void
     *
     * @throws SetupException Если patch недопустим.
     */
    private function ensureAssignOnRegister(int $groupId, bool $currentAssign, bool $assignOnRegister): void
    {
        if ($currentAssign === $assignOnRegister) {
            return;
        }

        try {
            $this->userGroups->update($groupId, GroupPatch::fromNormalized([
                'assign_on_register' => $assignOnRegister,
            ]));
        } catch (UserInvalidException $exception) {
            throw new SetupException('SETUP_INVALID', 'Cannot update seed group', $exception);
        }
    }

    /**
     * Находит или создаёт оператора.
     *
     * @return int Id учётки.
     *
     * @throws SetupException Если создать нельзя.
     */
    private function ensureOperator(): int
    {
        $login = $this->authSettings->operatorLogin();
        $existing = $this->userAccounts->findByLogin($login);
        if ($existing !== null) {
            return $existing->getId();
        }

        try {
            return $this->userAccounts->addFromInput([
                'login' => $login,
                'name' => $this->authSettings->operatorName(),
                'active' => true,
            ]);
        } catch (UserDuplicateException $exception) {
            throw new SetupException('SETUP_INVALID', 'Cannot create seed operator', $exception);
        } catch (UserInvalidException $exception) {
            throw new SetupException('SETUP_INVALID', 'Cannot create seed operator', $exception);
        }
    }

    /**
     * Добавляет членство, игнорируя дубль.
     *
     * @param int $userId Учётка.
     * @param int $groupId Группа.
     *
     * @return void
     */
    private function ensureMember(int $userId, int $groupId): void
    {
        try {
            $this->userGroups->addMember($userId, $groupId);
        } catch (UserDuplicateException $exception) {
            unset($exception);
        }
    }
}
