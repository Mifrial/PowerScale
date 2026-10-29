<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Container\IEventContainer;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionMask;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterRequirementFacts;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Service\AbilityRequirementCheck;

/**
 * Сборка удара. Не метод GamePortFactory.
 */
final class GameStrikePortFactory
{
    /**
     * Создаёт порт.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGameStrikes Фасад.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function create(IServiceLocator $serviceLocator): IGameStrikes
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $events = $this->events($serviceLocator);
        $card = new GameCardAccess(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            new GameInvitationRepository($gateway),
        );
        GameDeliveryListener::register(
            $events,
            new GameDelivery($gateway, $this->games($serviceLocator), $card),
        );
        $rules = $this->strikeRules($serviceLocator);

        return new GameStrikes(
            $gateway,
            $this->games($serviceLocator),
            $card,
            new GameSessionRepository($gateway),
            $this->mutations($serviceLocator),
            $rules,
            new ConflictSheetProjection(
                new CharacterSectionMask(),
                new GameCharacterProjectionMask(),
                new GameNpcVisibility(),
            ),
            $events,
            $this->consequences($serviceLocator, $rules),
            $this->cardAvailability($serviceLocator, $gateway, $rules),
        );
    }

    /**
     * Создаёт порт широкого удара тем же составом зависимостей.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGameWideStrikes Фасад.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function createWide(IServiceLocator $serviceLocator): IGameWideStrikes
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $events = $this->events($serviceLocator);
        $card = new GameCardAccess(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            new GameInvitationRepository($gateway),
        );
        GameDeliveryListener::register(
            $events,
            new GameDelivery($gateway, $this->games($serviceLocator), $card),
        );
        $rules = $this->strikeRules($serviceLocator);

        return new GameWideStrikes(
            $gateway,
            $this->games($serviceLocator),
            $card,
            new GameSessionRepository($gateway),
            $this->mutations($serviceLocator),
            $rules,
            $events,
            $this->consequences($serviceLocator, $rules),
            $this->cardAvailability($serviceLocator, $gateway, $rules),
        );
    }

    /**
     * HTTP единого входа действий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameRunActionHttp Сценарий.
     *
     * @throws KernelException Если порта нет.
     */
    public function createRunActionHttp(IServiceLocator $serviceLocator): GameRunActionHttp
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new GameRunActionHttp(
            $this->userAccess($serviceLocator),
            new GameRunAction(
                new GameRunActionBody(),
                $this->create($serviceLocator),
                $this->createWide($serviceLocator),
                $this->createActionDomain($serviceLocator),
                (new GameWoundPortFactory())->createActions($serviceLocator),
                new GameActionRepository($gateway),
                new GameCommandRepository($gateway),
                $this->games($serviceLocator),
                $this->strikeRules($serviceLocator),
            ),
        );
    }

    /**
     * HTTP опций действий участника.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameActionOptionsHttp Сценарий.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function createActionOptionsHttp(IServiceLocator $serviceLocator): GameActionOptionsHttp
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $rules = $this->strikeRules($serviceLocator);

        return new GameActionOptionsHttp(
            $this->userAccess($serviceLocator),
            $this->games($serviceLocator),
            new GameCardAccess(
                new GameRepository($gateway),
                new GameMemberRepository($gateway),
                new GameInvitationRepository($gateway),
            ),
            $rules,
            new GameStrikeSheets(
                $rules,
                new GameNpcRepository($gateway),
                new GameCharacterRepository($gateway),
                new GameMemberRepository($gateway),
                new ConflictSheetProjection(
                    new CharacterSectionMask(),
                    new GameCharacterProjectionMask(),
                    new GameNpcVisibility(),
                ),
            ),
        );
    }

    /**
     * Доменная команда карточки действия.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameActionDeclare Команда.
     *
     * @throws KernelException Если порта нет.
     */
    public function createActionDomain(IServiceLocator $serviceLocator): GameActionDeclare
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $events = $this->events($serviceLocator);
        $card = new GameCardAccess(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            new GameInvitationRepository($gateway),
        );
        GameDeliveryListener::register(
            $events,
            new GameDelivery($gateway, $this->games($serviceLocator), $card),
        );

        $chatContainer = $serviceLocator->get(IChatContainer::class);
        $chats = $chatContainer->get(IChats::class);
        if (!$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Chat service has a wrong type');
        }

        $rules = $this->strikeRules($serviceLocator);
        $npcs = new GameNpcRepository($gateway);
        $writes = new GameStrikeSheetWrites($this->mutations($serviceLocator), $npcs);
        $perform = new GameActionPerform(
            $rules,
            $npcs,
            $writes,
            new GameActionCards(),
            new GamePendingActionCosts(),
            $this->cardAvailability($serviceLocator, $gateway, $rules),
        );
        $strikeChat = new GameStrikeChat($npcs, $rules);
        $sheets = new GameStrikeSheets(
            $rules,
            $npcs,
            new GameCharacterRepository($gateway),
            new GameMemberRepository($gateway),
            new ConflictSheetProjection(
                new CharacterSectionMask(),
                new GameCharacterProjectionMask(),
                new GameNpcVisibility(),
            ),
        );

        return new GameActionDeclare(
            $gateway,
            $this->games($serviceLocator),
            $card,
            $perform,
            new GamePreparedDefense(),
            new GameActionChat($chats, $strikeChat),
            $sheets,
            $events,
        );
    }

    /**
     * HTTP конца хода. Тот же состав портов, что у удара.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameTurnHttp Сценарий.
     *
     * @throws KernelException Если порт чужой.
     */
    public function createTurnHttp(IServiceLocator $serviceLocator): GameTurnHttp
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $rules = $this->strikeRules($serviceLocator);

        $chatContainer = $serviceLocator->get(IChatContainer::class);
        $chats = $chatContainer->get(IChats::class);
        if (!$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Chat service has a wrong type');
        }

        $card = new GameCardAccess(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            new GameInvitationRepository($gateway),
        );
        $ticks = new GameTurnTicks(
            $this->checkRoll($serviceLocator),
            $this->consequences($serviceLocator, $rules),
            new GameStrikeSplits(),
            $this->formulaContexts($serviceLocator),
            $this->mechanics($serviceLocator),
            $this->engine($serviceLocator),
        );

        return new GameTurnHttp($this->userAccess($serviceLocator), new GameTurns(
            $gateway,
            $this->games($serviceLocator),
            $card,
            $rules,
            $this->mutations($serviceLocator),
            $chats,
            $ticks,
            $this->strikeOpen($serviceLocator, $gateway, $rules),
            $this->events($serviceLocator),
            new GameCombatNotices($chats),
            $this->cardAvailability($serviceLocator, $gateway, $rules),
        ));
    }

    /**
     * Гейт принадлежности и требований карточки действия.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param ISmartTableGateway $gateway Шлюз.
     * @param GameStrikeRules $rules Срез.
     *
     * @return GameCardAvailability Сервис.
     *
     * @throws KernelException Если нет порта листов.
     */
    private function cardAvailability(
        IServiceLocator $serviceLocator,
        ISmartTableGateway $gateway,
        GameStrikeRules $rules,
    ): GameCardAvailability {
        $npcs = new GameNpcRepository($gateway);

        return new GameCardAvailability(
            $rules,
            new GameStrikeSheets(
                $rules,
                $npcs,
                new GameCharacterRepository($gateway),
                new GameMemberRepository($gateway),
                new ConflictSheetProjection(
                    new CharacterSectionMask(),
                    new GameCharacterProjectionMask(),
                    new GameNpcVisibility(),
                ),
            ),
            $this->characterSheets($serviceLocator),
            new CharacterRequirementFacts(),
            new AbilityRequirementCheck(),
        );
    }

    /**
     * Открытие удара для конца хода: только порты записи.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param ISmartTableGateway $gateway Шлюз.
     * @param GameStrikeRules $rules Срез.
     *
     * @return GameStrikeOpen Сервис открытия.
     */
    private function strikeOpen(
        IServiceLocator $serviceLocator,
        ISmartTableGateway $gateway,
        GameStrikeRules $rules,
    ): GameStrikeOpen {
        $npcs = new GameNpcRepository($gateway);
        $sheetWrites = new GameStrikeSheetWrites($this->mutations($serviceLocator), $npcs);

        return new GameStrikeOpen(
            new GameBattleRepository($gateway),
            new GameActionRepository($gateway),
            new GameStrikeSpend($rules, $sheetWrites),
            $rules,
            new GameStrikeSheets(
                $rules,
                $npcs,
                new GameCharacterRepository($gateway),
                new GameMemberRepository($gateway),
                new ConflictSheetProjection(
                    new CharacterSectionMask(),
                    new GameCharacterProjectionMask(),
                    new GameNpcVisibility(),
                ),
            ),
            $sheetWrites,
        );
    }

    /**
     * Шлюз таблиц.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если порта нет.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Game requires ISmartTableGateway');
        }

        return $gateway;
    }

    /**
     * Строка игры.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IGames Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    private function games(IServiceLocator $serviceLocator): IGames
    {
        $games = $serviceLocator->get(IGameContainer::class)->get(IGames::class);
        if (!$games instanceof IGames) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IGames');
        }

        return $games;
    }

    /**
     * Порт листа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterActualMutations Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function mutations(IServiceLocator $serviceLocator): ICharacterActualMutations
    {
        $mutations = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterActualMutations::class);
        if (!$mutations instanceof ICharacterActualMutations) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterActualMutations');
        }

        return $mutations;
    }

    /**
     * Срез правил.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterRuleSlices Срез.
     *
     * @throws KernelException Если порта нет.
     */
    private function ruleSlices(IServiceLocator $serviceLocator): ICharacterRuleSlices
    {
        $ruleSlices = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterRuleSlices::class);
        if (!$ruleSlices instanceof ICharacterRuleSlices) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterRuleSlices');
        }

        return $ruleSlices;
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
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacters');
        }

        return $characters;
    }

    /**
     * Контекст формулы из листа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterFormulaContexts Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function formulaContexts(IServiceLocator $serviceLocator): ICharacterFormulaContexts
    {
        $contexts = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterFormulaContexts::class);
        if (!$contexts instanceof ICharacterFormulaContexts) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterFormulaContexts');
        }

        return $contexts;
    }

    /**
     * Полный пересчёт листа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterSheets Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function characterSheets(IServiceLocator $serviceLocator): ICharacterSheets
    {
        $sheets = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterSheets::class);
        if (!$sheets instanceof ICharacterSheets) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterSheets');
        }

        return $sheets;
    }

    /**
     * Правила удара со слоями.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameStrikeRules Правила.
     *
     * @throws KernelException Если порта нет.
     */
    private function consequences(IServiceLocator $serviceLocator, GameStrikeRules $rules): GameStrikeConsequences
    {
        $chatContainer = $serviceLocator->get(IChatContainer::class);
        $chats = $chatContainer->get(IChats::class);
        if (!$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Chat service has a wrong type');
        }

        return new GameStrikeConsequences(
            $rules,
            $this->checkRoll($serviceLocator),
            $this->formulaContexts($serviceLocator),
            $this->mechanics($serviceLocator),
            $this->engine($serviceLocator),
            $chats,
        );
    }

    /**
     * Правила удара для соседней команды раны.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameStrikeRules Правила.
     *
     * @throws KernelException Если порта нет.
     */
    public function strikeRulesForWound(IServiceLocator $serviceLocator): GameStrikeRules
    {
        return $this->strikeRules($serviceLocator);
    }

    /**
     * Правила удара со слоями.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameStrikeRules Правила.
     *
     * @throws KernelException Если порта нет.
     */
    private function strikeRules(IServiceLocator $serviceLocator): GameStrikeRules
    {
        return new GameStrikeRules(
            $this->ruleSlices($serviceLocator),
            $this->characters($serviceLocator),
            $this->formulaContexts($serviceLocator),
            $this->formulaEvaluations($serviceLocator),
            $this->checkRoll($serviceLocator),
            $this->layers($serviceLocator),
            $this->characterSheets($serviceLocator),
        );
    }

    /**
     * Сопротивление слоёв.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameStrikeLayers Расчёт.
     *
     * @throws KernelException Если порта нет.
     */
    private function layers(IServiceLocator $serviceLocator): GameStrikeLayers
    {
        return new GameStrikeLayers(
            $this->combatLayers($serviceLocator),
            $this->engine($serviceLocator),
            $this->mechanics($serviceLocator),
            new GameStrikeAmounts(),
        );
    }

    /**
     * Проекция слоёв.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterCombatLayers Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function combatLayers(IServiceLocator $serviceLocator): ICharacterCombatLayers
    {
        $layers = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterCombatLayers::class);
        if (!$layers instanceof ICharacterCombatLayers) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterCombatLayers');
        }

        return $layers;
    }

    /**
     * Движок механик.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IMechanicEngine Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function engine(IServiceLocator $serviceLocator): IMechanicEngine
    {
        $engine = $serviceLocator->get(IMechanicContainer::class)->get(IMechanicEngine::class);
        if (!$engine instanceof IMechanicEngine) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IMechanicEngine');
        }

        return $engine;
    }

    /**
     * Бросок попадания.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameCheckRoll Расчёт.
     *
     * @throws KernelException Если порта нет.
     */
    private function checkRoll(IServiceLocator $serviceLocator): GameCheckRoll
    {
        return new GameCheckRoll(
            $this->ruleSlices($serviceLocator),
            $this->characters($serviceLocator),
            $this->mechanics($serviceLocator),
            $this->rolls($serviceLocator),
            $this->characterSheets($serviceLocator),
        );
    }

    /**
     * Каталог механик.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IMechanics Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function mechanics(IServiceLocator $serviceLocator): IMechanics
    {
        $mechanics = $serviceLocator->get(IMechanicContainer::class)->get(IMechanics::class);
        if (!$mechanics instanceof IMechanics) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IMechanics');
        }

        return $mechanics;
    }

    /**
     * Порт броска.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IMechanicRolls Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function rolls(IServiceLocator $serviceLocator): IMechanicRolls
    {
        $rolls = $serviceLocator->get(IMechanicContainer::class)->get(IMechanicRolls::class);
        if (!$rolls instanceof IMechanicRolls) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IMechanicRolls');
        }

        return $rolls;
    }

    /**
     * Обход формулы.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IFormulaEvaluations Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function formulaEvaluations(IServiceLocator $serviceLocator): IFormulaEvaluations
    {
        $evaluations = $serviceLocator->get(IRuleContainer::class)->get(IFormulaEvaluations::class);
        if (!$evaluations instanceof IFormulaEvaluations) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IFormulaEvaluations');
        }

        return $evaluations;
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
    private function events(IServiceLocator $serviceLocator): IEventManager
    {
        $events = $serviceLocator->get(IEventContainer::class)->get(IEventManager::class);
        if (!$events instanceof IEventManager) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IEventManager');
        }

        return $events;
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
            throw new KernelException('PORT_TYPE', 'Game strike requires IUserAccess');
        }

        return $userAccess;
    }
}
