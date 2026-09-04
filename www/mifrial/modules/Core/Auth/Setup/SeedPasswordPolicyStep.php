<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Setup;

use Mifrial\Core\Auth\Repository\GroupSecurityPolicyRepository;
use Mifrial\Core\Auth\Repository\PasswordPolicyRepository;
use Mifrial\Core\Auth\Service\PasswordPolicyService;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\User\Interface\Service\IUserGroups;

/**
 * Default-политика и связь с assign_on_register.
 */
final class SeedPasswordPolicyStep implements ISetupStep
{
    /**
     * Создаёт шаг.
     *
     * @param PasswordPolicyRepository $policyRepository Каталог.
     * @param GroupSecurityPolicyRepository $groupPolicyRepository Связи.
     * @param IUserGroups $userGroups Группы.
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
     * Возвращает id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Core/Auth:seed.password-policy';
    }

    /**
     * Пишет default и привязки, если их нет.
     *
     * @return void
     */
    public function run(): void
    {
        (new PasswordPolicyService(
            $this->policyRepository,
            $this->groupPolicyRepository,
            $this->userGroups,
        ))->ensureDefaults();
    }
}
