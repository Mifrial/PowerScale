<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Setup;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Repository\GroupSecurityPolicyRepository;
use Mifrial\Core\Auth\Repository\PasswordPolicyRepository;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Auth\Schema\AuthSchema;
use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;

/**
 * Карты Auth и data-шаги: политика пароля, seed групп.
 */
final class AuthModuleSetup implements IModuleSetup
{
    /**
     * Создаёт setup.
     *
     * @param IUserAccounts $userAccounts Учётки.
     * @param IUserGroups $userGroups Группы.
     * @param UserIdentityRepository $identityRepository Identity.
     * @param PasswordPolicyRepository $policyRepository Каталог политик.
     * @param GroupSecurityPolicyRepository $groupPolicyRepository Связи.
     * @param AuthSettings $authSettings Срез auth.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccounts $userAccounts,
        private readonly IUserGroups $userGroups,
        private readonly UserIdentityRepository $identityRepository,
        private readonly PasswordPolicyRepository $policyRepository,
        private readonly GroupSecurityPolicyRepository $groupPolicyRepository,
        private readonly AuthSettings $authSettings,
    ) {
    }

    /**
     * Возвращает карты Auth.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return AuthSchema::getTableClasses();
    }

    /**
     * Seed политики, групп и оператора.
     *
     * @return array<int, ISetupStep> Шаги.
     */
    public function getDataSteps(): array
    {
        return [
            new BootstrapGroupsStep(
                $this->userAccounts,
                $this->userGroups,
                $this->identityRepository,
                $this->authSettings,
            ),
            new SeedPasswordPolicyStep(
                $this->policyRepository,
                $this->groupPolicyRepository,
                $this->userGroups,
            ),
        ];
    }
}
