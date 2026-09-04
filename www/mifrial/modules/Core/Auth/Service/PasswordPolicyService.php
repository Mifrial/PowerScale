<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Dto\PasswordPolicy;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Exception\AuthPolicyException;
use Mifrial\Core\Auth\Repository\GroupSecurityPolicyRepository;
use Mifrial\Core\Auth\Repository\PasswordPolicyRepository;
use Mifrial\Core\User\Interface\Service\IUserGroups;

/**
 * Default и effective политика пароля.
 */
final class PasswordPolicyService
{
    /**
     * Создаёт сервис.
     *
     * @param PasswordPolicyRepository $policyRepository Каталог.
     * @param GroupSecurityPolicyRepository $groupPolicyRepository Связи групп.
     * @param IUserGroups $userGroups Группы User.
     *
     * @return void
     */
    public function __construct(
        private readonly PasswordPolicyRepository $policyRepository,
        private readonly GroupSecurityPolicyRepository $groupPolicyRepository,
        private readonly IUserGroups $userGroups,
    ) {
    }

    /**
     * JSON: default или effective учётки.
     *
     * @param int|null $userId Учётка или нет.
     *
     * @return array{minLength: int, requireMixedCase: bool, requireDigit: bool, requireSpecialChar: bool}
     *
     * @throws AuthInvalidException Если default нет.
     */
    public function getPasswordPolicy(?int $userId = null): array
    {
        if ($userId === null || $userId < 1) {
            return $this->defaultPolicy()->toJson();
        }

        return $this->policyForUser($userId)->toJson();
    }

    /**
     * Режет пароль default-политикой (register, forgot UI).
     *
     * @param string $password Пароль.
     *
     * @return void
     *
     * @throws AuthInvalidException Если default нет.
     * @throws AuthPolicyException Если пароль слабый.
     */
    public function assertPassword(string $password): void
    {
        $this->defaultPolicy()->assertPassword($password);
    }

    /**
     * Режет пароль effective политикой учётки.
     *
     * @param int $userId Учётка.
     * @param string $password Пароль.
     *
     * @return void
     *
     * @throws AuthInvalidException Если default нет.
     * @throws AuthPolicyException Если пароль слабый.
     */
    public function assertPasswordForUser(int $userId, string $password): void
    {
        $this->policyForUser($userId)->assertPassword($password);
    }

    /**
     * Режет пароль по набору групп (user.create до членства).
     *
     * @param array<int, int> $groupIds Группы.
     * @param string $password Пароль.
     *
     * @return void
     *
     * @throws AuthInvalidException Если default нет.
     * @throws AuthPolicyException Если пароль слабый.
     */
    public function assertPasswordForGroupIds(array $groupIds, string $password): void
    {
        $this->policyForGroupIds($groupIds)->assertPassword($password);
    }

    /**
     * Default-строка и связи assign_on_register.
     *
     * @return void
     */
    public function ensureDefaults(): void
    {
        $policyId = $this->policyRepository->ensureDefaultRow();
        foreach ($this->userGroups->getAssignOnRegisterIds() as $groupId) {
            $this->groupPolicyRepository->bindIfAbsent($groupId, $policyId);
        }
    }

    /**
     * Default из БД.
     *
     * @return PasswordPolicy Срез.
     *
     * @throws AuthInvalidException Если нет default.
     */
    private function defaultPolicy(): PasswordPolicy
    {
        $policyRow = $this->policyRepository->findDefault();
        if ($policyRow === null) {
            throw new AuthInvalidException('Password policy is missing');
        }

        return PasswordPolicy::fromRow($policyRow);
    }

    /**
     * Effective учётки.
     *
     * @param int $userId Учётка.
     *
     * @return PasswordPolicy Срез.
     *
     * @throws AuthInvalidException Если default нет.
     */
    private function policyForUser(int $userId): PasswordPolicy
    {
        return $this->policyForGroupIds($this->userGroups->getGroupIdsOfUser($userId));
    }

    /**
     * Наибольшая среди активных групп; иначе default.
     *
     * @param array<int, int> $groupIds Группы.
     *
     * @return PasswordPolicy Срез.
     *
     * @throws AuthInvalidException Если default нет.
     */
    private function policyForGroupIds(array $groupIds): PasswordPolicy
    {
        $activeIds = $this->activeGroupIds($groupIds);
        $policies = [];
        foreach ($this->groupPolicyRepository->findPolicyIdsByGroupIds($activeIds) as $policyId) {
            $policyRow = $this->policyRepository->findById($policyId);
            if (is_array($policyRow)) {
                $policies[] = PasswordPolicy::fromRow($policyRow);
            }
        }

        if ($policies === []) {
            return $this->defaultPolicy();
        }

        return PasswordPolicy::strictest($policies);
    }

    /**
     * Только активные группы.
     *
     * @param array<int, int> $groupIds Кандидаты.
     *
     * @return array<int, int> Id.
     */
    private function activeGroupIds(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $activeIds = [];
        foreach ($this->userGroups->getByIds($groupIds) as $groupRecord) {
            if ($groupRecord->isActive()) {
                $activeIds[] = $groupRecord->getId();
            }
        }

        return $activeIds;
    }
}
