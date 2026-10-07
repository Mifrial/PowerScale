<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSessionParticipants;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheetEngines;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Repository\CharacterRepository;
use Mifrial\Roleplay\Character\Repository\CharacterVisibilityRepository;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionCodes;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionMask;
use Mifrial\Roleplay\Character\Service\Read\CharacterViewAssembler;
use Mifrial\Roleplay\Character\Service\Read\CharacterViewerParser;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Save\CharacterInputNormalizer;
use Mifrial\Roleplay\Character\Service\Save\CharacterSaveAssembly;
use Mifrial\Roleplay\Character\Service\Save\CharacterSheetDocument;
use Mifrial\Roleplay\Character\Service\Save\CharacterShopBalance;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterSpecReader;

/**
 * Сборка фасада персонажа из локатора.
 */
final class CharacterPortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ICharacters Фасад.
     *
     * @throws KernelException Если нет шлюза или учёток.
     */
    public function create(IServiceLocator $serviceLocator): ICharacters
    {
        return new Characters(
            new CharacterRepository($this->smartTableGateway($serviceLocator)),
            $this->userAccounts($serviceLocator),
            new CharacterInputNormalizer(),
        );
    }

    /**
     * Сценарий HTTP save.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return CharacterSave Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createSave(IServiceLocator $serviceLocator): CharacterSave
    {
        return new CharacterSave(
            $this->userAccess($serviceLocator),
            $this->create($serviceLocator),
            $this->ruleSlices($serviceLocator),
            $this->sheets($serviceLocator),
            new CharacterShopBalance(new CharacterSpecReader(), new CharacterDonorGrants()),
        );
    }

    /**
     * Сценарий миграции ревизии.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param ICharacterSessionParticipants $sessionParticipants Участник сессии.
     *
     * @return CharacterMigration Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createMigration(
        IServiceLocator $serviceLocator,
        ICharacterSessionParticipants $sessionParticipants,
    ): CharacterMigration {
        $ruleSlices = $this->ruleSlices($serviceLocator);
        $shopBalance = new CharacterShopBalance(new CharacterSpecReader(), new CharacterDonorGrants());

        return new CharacterMigration(
            $this->userAccess($serviceLocator),
            $this->create($serviceLocator),
            $ruleSlices,
            new CharacterSaveAssembly($ruleSlices, $this->sheets($serviceLocator), $shopBalance),
            $sessionParticipants,
        );
    }

    /**
     * Сборка листа без записи. Её зовёт Game для NPC.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ICharacterSheetEngines Порт.
     *
     * @throws KernelException Если нет порта.
     */
    public function createSheetEngine(IServiceLocator $serviceLocator): ICharacterSheetEngines
    {
        $ruleSlices = $this->ruleSlices($serviceLocator);
        $shopBalance = new CharacterShopBalance(new CharacterSpecReader(), new CharacterDonorGrants());

        return new CharacterSheetEngine(
            $ruleSlices,
            new CharacterSaveAssembly($ruleSlices, $this->sheets($serviceLocator), $shopBalance),
        );
    }

    /**
     * Порт точечной записи actual. Владельца не проверяет.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ICharacterActualMutations Порт.
     *
     * @throws KernelException Если нет порта.
     */
    public function createActualMutation(IServiceLocator $serviceLocator): ICharacterActualMutations
    {
        return new CharacterActualMutations(
            $this->create($serviceLocator),
            $this->ruleSlices($serviceLocator),
            $this->sheets($serviceLocator),
            new CharacterChoiceAssembler(),
            new CharacterSheetDocument(),
        );
    }

    /**
     * HTTP-сценарий патча: актор и владелец, затем порт.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return CharacterActualPatch Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createActualPatch(IServiceLocator $serviceLocator): CharacterActualPatch
    {
        return new CharacterActualPatch(
            $this->userAccess($serviceLocator),
            $this->create($serviceLocator),
            $this->createActualMutation($serviceLocator),
        );
    }

    /**
     * Сценарий HTTP чтения.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return CharacterRead Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createRead(IServiceLocator $serviceLocator): CharacterRead
    {
        $sectionCodes = new CharacterSectionCodes();

        return new CharacterRead(
            $this->userAccess($serviceLocator),
            new CharacterSheetAccess(
                new CharacterVisibilityRepository($this->smartTableGateway($serviceLocator)),
                $sectionCodes,
                new CharacterViewerParser($sectionCodes),
            ),
            new CharacterViewAssembler(new CharacterSectionMask()),
        );
    }

    /**
     * Шлюз ST.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $smartTableGateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Character requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }

    /**
     * Учётки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccounts Фасад User.
     *
     * @throws KernelException Если тип чужой.
     */
    private function userAccounts(IServiceLocator $serviceLocator): IUserAccounts
    {
        $userAccounts = $serviceLocator->get(IUserContainer::class)->get(IUserAccounts::class);
        if (!$userAccounts instanceof IUserAccounts) {
            throw new KernelException('PORT_TYPE', 'Character requires IUserAccounts');
        }

        return $userAccounts;
    }

    /**
     * Guard учёток.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если тип чужой.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Character HTTP requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Срез правил.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterRuleSlices Порт.
     *
     * @throws KernelException Если тип чужой.
     */
    private function ruleSlices(IServiceLocator $serviceLocator): ICharacterRuleSlices
    {
        return (new CharacterRuleSlicePortFactory())->create($serviceLocator);
    }

    /**
     * Валидатор листа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterSheets Порт.
     *
     * @throws KernelException Если тип чужой.
     */
    private function sheets(IServiceLocator $serviceLocator): ICharacterSheets
    {
        return (new CharacterSheetPortFactory())->create($serviceLocator);
    }
}
