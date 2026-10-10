<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Interface\Service\IChatTypeRegistry;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSessionParticipants;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheetEngines;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Interface\Service\IGameAdmissions;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameJoinRequestRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

/**
 * Сборка фасада игры из локатора.
 */
final class GamePortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGames Фасад.
     *
     * @throws KernelException Если нет шлюза, учёток или миров.
     */
    public function create(IServiceLocator $serviceLocator): IGames
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new Games(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            $this->userAccounts($serviceLocator),
            $this->worldGate($serviceLocator),
            new GameInputNormalizer(),
            $this->sessionRoster($serviceLocator),
            $gateway,
            $this->donorChats($serviceLocator),
        );
    }

    /**
     * Сценарий HTTP.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createHttp(IServiceLocator $serviceLocator): GameHttp
    {
        return new GameHttp(
            $this->userAccess($serviceLocator),
            $this->create($serviceLocator),
            $this->worldGate($serviceLocator),
            new GameViewAssembler(),
            $this->sessionRoster($serviceLocator),
            $this->cardAccess($serviceLocator),
        );
    }

    /**
     * Порт вступления.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGameAdmissions Порт.
     *
     * @throws KernelException Если нет шлюза или учёток.
     */
    public function createAdmissions(IServiceLocator $serviceLocator): IGameAdmissions
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new GameAdmissions(
            $this->create($serviceLocator),
            $this->userAccounts($serviceLocator),
            new GameRepository($gateway),
            new GameInvitationRepository($gateway),
            new GameJoinRequestRepository($gateway),
            $this->cardAccess($serviceLocator),
        );
    }

    /**
     * HTTP вступления.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameAdmissionHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createAdmissionHttp(IServiceLocator $serviceLocator): GameAdmissionHttp
    {
        return new GameAdmissionHttp(
            $this->userAccess($serviceLocator),
            $this->createAdmissions($serviceLocator),
        );
    }

    /**
     * Фасад строки персонажа.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGameMemberships Фасад.
     *
     * @throws KernelException Если нет шлюза или персонажей.
     */
    public function createMemberships(IServiceLocator $serviceLocator): IGameMemberships
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new GameCharacterMemberships(
            new GameCharacterRepository($gateway),
            $this->create($serviceLocator),
            $this->characters($serviceLocator),
            $this->review($serviceLocator),
            $this->donorChats($serviceLocator),
            $gateway,
        );
    }

    /**
     * HTTP секций строки персонажа.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameCharacterSectionHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createCharacterSectionHttp(IServiceLocator $serviceLocator): GameCharacterSectionHttp
    {
        return new GameCharacterSectionHttp(
            new GameCharacterSections(
                $this->userAccess($serviceLocator),
                $this->create($serviceLocator),
                new GameCharacterRepository($this->smartTableGateway($serviceLocator)),
            ),
            $this->createMemberships($serviceLocator),
            new GameCharacterViewAssembler(),
        );
    }

    /**
     * HTTP строки персонажа.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameCharacterHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createCharacterHttp(IServiceLocator $serviceLocator): GameCharacterHttp
    {
        return new GameCharacterHttp(
            $this->userAccess($serviceLocator),
            $this->create($serviceLocator),
            $this->createMemberships($serviceLocator),
            $this->characters($serviceLocator),
            $this->review($serviceLocator),
            new GameCharacterViewAssembler(),
        );
    }

    /**
     * Старт и stop.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameSessionHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createSessionHttp(IServiceLocator $serviceLocator): GameSessionHttp
    {
        return new GameSessionHttp(
            $this->userAccess($serviceLocator),
            $this->create($serviceLocator),
            $this->sessions($serviceLocator),
            $this->sessionRoster($serviceLocator),
            new GameViewAssembler(),
        );
    }

    /**
     * Порт migrate: персонаж в текущей сессии.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ICharacterSessionParticipants Чтение состава.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function createSessionParticipants(IServiceLocator $serviceLocator): ICharacterSessionParticipants
    {
        return $this->sessionRoster($serviceLocator);
    }

    /**
     * HTTP NPC.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameNpcHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createNpcHttp(IServiceLocator $serviceLocator): GameNpcHttp
    {
        $visibility = new GameNpcVisibility();

        return new GameNpcHttp(
            $this->userAccess($serviceLocator),
            $this->create($serviceLocator),
            new GameNpcs(
                new GameNpcRepository($this->smartTableGateway($serviceLocator)),
                $this->create($serviceLocator),
                $this->sheetEngines($serviceLocator),
                $this->smartTableGateway($serviceLocator),
            ),
            $visibility,
            new GameNpcView($visibility),
        );
    }

    /**
     * Чаты донора на реестре контейнера Chat.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameDonorChats Сборщик.
     *
     * @throws KernelException Если порта нет.
     */
    private function donorChats(IServiceLocator $serviceLocator): GameDonorChats
    {
        $chatContainer = $serviceLocator->get(IChatContainer::class);
        if (!$chatContainer instanceof IChatContainer) {
            throw new KernelException('PORT_TYPE', 'Game requires IChatContainer');
        }

        $registry = $chatContainer->get(IChatTypeRegistry::class);
        $chats = $chatContainer->get(IChats::class);
        if (!$registry instanceof IChatTypeRegistry || !$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Game requires chat ports');
        }

        return new GameDonorChats(
            $registry,
            $chats,
            new GameCharacterRepository($this->smartTableGateway($serviceLocator)),
        );
    }

    /**
     * Шлюз SmartTable.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если тип неверен.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $smartTableGateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Game requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }

    /**
     * Учётки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccounts Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    private function userAccounts(IServiceLocator $serviceLocator): IUserAccounts
    {
        $userAccounts = $serviceLocator->get(IUserContainer::class)->get(IUserAccounts::class);
        if (!$userAccounts instanceof IUserAccounts) {
            throw new KernelException('PORT_TYPE', 'Game requires IUserAccounts');
        }

        return $userAccounts;
    }

    /**
     * Допуск строки персонажа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameCharacterReview Компаратор и лист.
     *
     * @throws KernelException Если порта нет.
     */
    private function review(IServiceLocator $serviceLocator): GameCharacterReview
    {
        return new GameCharacterReview(
            new GameCharacterDiff(),
            $this->sheets($serviceLocator),
            $this->sessionRoster($serviceLocator),
        );
    }

    /**
     * Чтение сессии. Контейнер Character не открывает.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameSessionRoster Состав.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function sessionRoster(IServiceLocator $serviceLocator): GameSessionRoster
    {
        return new GameSessionRoster(new GameSessionRepository($this->smartTableGateway($serviceLocator)));
    }

    /**
     * Старт и stop без HTTP.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameSessions Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    private function sessions(IServiceLocator $serviceLocator): GameSessions
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $sessionRepository = new GameSessionRepository($gateway);

        return new GameSessions(
            $this->create($serviceLocator),
            $this->createMemberships($serviceLocator),
            $this->characters($serviceLocator),
            $this->review($serviceLocator),
            $sessionRepository,
            new GameBattleCleanup($gateway, $sessionRepository),
        );
    }

    /**
     * Проверка листа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterSheets Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function sheets(IServiceLocator $serviceLocator): ICharacterSheets
    {
        $sheets = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterSheets::class);
        if (!$sheets instanceof ICharacterSheets) {
            throw new KernelException('PORT_TYPE', 'Game requires ICharacterSheets');
        }

        return $sheets;
    }

    /**
     * Сборка листа NPC.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterSheetEngines Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function sheetEngines(IServiceLocator $serviceLocator): ICharacterSheetEngines
    {
        $sheetEngines = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterSheetEngines::class);
        if (!$sheetEngines instanceof ICharacterSheetEngines) {
            throw new KernelException('PORT_TYPE', 'Game requires ICharacterSheetEngines');
        }

        return $sheetEngines;
    }

    /**
     * Строки персонажа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacters Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    private function characters(IServiceLocator $serviceLocator): ICharacters
    {
        $characters = $serviceLocator->get(ICharacterContainer::class)->get(ICharacters::class);
        if (!$characters instanceof ICharacters) {
            throw new KernelException('PORT_TYPE', 'Game requires ICharacters');
        }

        return $characters;
    }

    /**
     * Актор запроса.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Доступ.
     *
     * @throws KernelException Если порта нет.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Game HTTP requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Фильтр карточки.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameCardAccess Разбор.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function cardAccess(IServiceLocator $serviceLocator): GameCardAccess
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new GameCardAccess(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            new GameInvitationRepository($gateway),
        );
    }

    /**
     * Сверка мира.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameWorldGate Сверка.
     *
     * @throws KernelException Если нет миров.
     */
    private function worldGate(IServiceLocator $serviceLocator): GameWorldGate
    {
        $ruleSpaces = $serviceLocator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
        if (!$ruleSpaces instanceof IRuleSpaces) {
            throw new KernelException('PORT_TYPE', 'Game requires IRuleSpaces');
        }

        return new GameWorldGate($ruleSpaces);
    }
}
