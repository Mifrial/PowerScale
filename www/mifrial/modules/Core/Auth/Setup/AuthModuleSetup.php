<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Setup;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Auth\Schema\AuthSchema;
use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;

/**
 * Карты Auth и data-шаг seed групп.
 */
final class AuthModuleSetup implements IModuleSetup
{
    /**
     * Создаёт setup.
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
     * Возвращает две карты Auth.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function tableClasses(): array
    {
        return AuthSchema::tableClasses();
    }

    /**
     * Seed групп и оператора.
     *
     * @return array<int, ISetupStep> Шаги.
     */
    public function dataSteps(): array
    {
        return [
            new BootstrapGroupsStep(
                $this->userAccounts,
                $this->userGroups,
                $this->identityRepository,
                $this->authSettings,
            ),
        ];
    }
}
