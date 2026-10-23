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
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;

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
        );
    }

    /**
     * HTTP удара.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameStrikeHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createHttp(IServiceLocator $serviceLocator): GameStrikeHttp
    {
        return new GameStrikeHttp($this->userAccess($serviceLocator), $this->create($serviceLocator));
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
        );
    }

    /**
     * HTTP широкого удара.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameWideStrikeHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createWideHttp(IServiceLocator $serviceLocator): GameWideStrikeHttp
    {
        return new GameWideStrikeHttp($this->userAccess($serviceLocator), $this->createWide($serviceLocator));
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
        $wide = $serviceLocator->get(IGameContainer::class)->get(IGameWideStrikes::class);
        $strikes = $this->create($serviceLocator);
        if (!$wide instanceof GameWideStrikes || !$strikes instanceof GameStrikes) {
            throw new KernelException('PORT_TYPE', 'Game turn requires strike services');
        }

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
            $strikes,
            $wide,
            $this->events($serviceLocator),
            new GameCombatNotices($chats),
        ));
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
