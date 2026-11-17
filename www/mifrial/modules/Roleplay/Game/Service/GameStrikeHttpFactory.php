<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterKeywordExperience;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameActiveEffectRepository;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * HTTP-сценарии ударного домена.
 */
final class GameStrikeHttpFactory
{
    /**
     * HTTP единого входа действий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameRunActionHttp Сценарий.
     */
    public function createRunActionHttp(IServiceLocator $serviceLocator): GameRunActionHttp
    {
        $ports = new GameStrikePorts($serviceLocator);
        $gateway = $ports->gateway();
        $domain = new GameStrikePortFactory();
        $strikes = $domain->create($serviceLocator);
        $wideStrikes = $domain->createWide($serviceLocator);
        $rules = (new GameStrikeRuleWiring($serviceLocator))->rules();
        $wounds = (new GameWoundPortFactory())->createActions($serviceLocator);
        $repository = new GameActionRepository($gateway);
        $commands = new GameCommandRepository($gateway);

        return new GameRunActionHttp(
            $ports->userAccess(),
            new GameRunAction(
                new GameRunActionBody(),
                new GameRunActionDeclare(
                    $this->actionDomain($serviceLocator),
                    $wounds,
                    new GameRunActionSpellDeclare($this->spellCast($serviceLocator), new GameSpellCastBody()),
                ),
                new GameRunActionStrikeDeclare($strikes, $wideStrikes, $ports->games(), $rules),
                new GameRunActionRespond($strikes, $wideStrikes, $repository, $commands),
                new GameRunActionSpellPhase($this->spellCast($serviceLocator)),
            ),
        );
    }

    /**
     * HTTP серверного каста заклинания.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameSpellCastHttp Сценарий.
     */
    public function createSpellCastHttp(IServiceLocator $serviceLocator): GameSpellCastHttp
    {
        return new GameSpellCastHttp(
            (new GameStrikePorts($serviceLocator))->userAccess(),
            new GameSpellCastBody(),
            $this->spellCast($serviceLocator),
        );
    }

    /**
     * Команда каста заклинания.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameSpellCast Команда.
     */
    private function spellCast(IServiceLocator $serviceLocator): GameSpellCast
    {
        $ports = new GameStrikePorts($serviceLocator);
        $gateway = $ports->gateway();
        $ruleWiring = new GameStrikeRuleWiring($serviceLocator);
        $rules = $ruleWiring->rules();
        $write = new GameStrikeWriteWiring($serviceLocator);
        $characters = new GameStrikeCharacterPorts($serviceLocator);
        $shared = new GameStrikeShared(
            $gateway,
            $ports->games(),
            $ports->mutations(),
            $rules,
            (new GameStrikeCardWiring())->card($serviceLocator),
            $ports->events(),
        );
        $magicResistance = new GameSpellMagicResistance($rules->defense);
        $mechanicPorts = new GameStrikeMechanicPorts($serviceLocator);
        $deviation = new GameSpellDeviation($mechanicPorts->mechanics());
        $facts = new GameSpellCastFacts(
            $rules,
            $shared->sheets,
            $characters->characterSheets(),
            $write->availability($rules),
            new GameActionResourceResolver(),
            new GameActionHands($rules, $shared->repos->characters, $shared->repos->npcs),
            $deviation,
            $shared->repos->activeSpells,
        );
        $activeGuard = new GameActiveSpellGuard($shared->repos->activeSpells);
        $activeCreate = new GameActiveSpellCreate(
            $shared->repos->activeSpells,
            $activeGuard,
            new GameSpellDurationRules(),
        );
        $activeChat = new GameActiveSpellChat($ports->chats(), $shared->chat);
        $effects = new GameActiveEffectWiring($serviceLocator, $shared);
        $chainHop = new GameSpellChainHop();
        $chainPending = new GameSpellChainPending();
        $chainDamage = new GameSpellChainDamage(
            $rules,
            $shared->sheets,
            $characters->characterSheets(),
            $rules->defense,
            new GameStrikeAmounts(),
            $magicResistance,
            $ruleWiring->consequences($rules),
            $chainHop,
            $chainPending,
        );

        return new GameSpellCast(
            $shared->ready,
            $shared->replay,
            $shared->repos->commands,
            $shared->announce,
            $shared->repos,
            $shared->access,
            $write->perform($rules),
            $facts,
            new GameSpellTouchFacts($facts, $shared->sheets, $activeGuard),
            new GameSpellCastCheck(
                $ruleWiring->checkRoll(),
                new GameCheckChain(),
                $magicResistance,
            ),
            new GameSpellCastDamage(
                $rules,
                new CharacterKeywordExperience(),
                new GameStrikeAmounts(),
                $magicResistance,
                $ruleWiring->consequences($rules),
            ),
            new GameSpellCastWrite($shared->sheetWrites),
            new GameSpellCastChat($ports->chats(), $shared->chat),
            new GameSpellCastView(),
            new GameSpellCastSaturation(),
            new GameSpellCastNextCast(),
            new GameSpellCastPending(),
            $shared->sheetWrites,
            $deviation,
            new GameSpellDeviationBurst(
                $rules,
                $shared->sheets,
                $characters->characterSheets(),
                $rules->defense,
                new GameStrikeAmounts(),
                $ruleWiring->consequences($rules),
            ),
            $activeGuard,
            $activeCreate,
            $activeChat,
            new GameActiveSpellCharge(new GameSpellCharge(), $shared->sheetWrites, $activeChat),
            $effects->create,
            $effects->chat,
            $chainPending,
            $chainDamage,
            $chainHop,
        );
    }

    /**
     * HTTP решения окна удара.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameStrikeWindowHttp Сценарий.
     */
    public function createWindowHttp(IServiceLocator $serviceLocator): GameStrikeWindowHttp
    {
        return new GameStrikeWindowHttp(
            (new GameStrikePorts($serviceLocator))->userAccess(),
            (new GameStrikePortFactory())->create($serviceLocator),
        );
    }

    /**
     * HTTP опций действий участника.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameActionOptionsHttp Сценарий.
     */
    public function createActionOptionsHttp(IServiceLocator $serviceLocator): GameActionOptionsHttp
    {
        $ports = new GameStrikePorts($serviceLocator);
        $rules = (new GameStrikeRuleWiring($serviceLocator))->rules();

        return new GameActionOptionsHttp(
            $ports->userAccess(),
            $ports->games(),
            (new GameStrikeCardWiring())->card($serviceLocator),
            $rules,
            (new GameStrikeWriteWiring($serviceLocator))->sheets($rules),
        );
    }

    /**
     * HTTP конца хода. Тот же состав портов, что у удара.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameTurnHttp Сценарий.
     */
    public function createTurnHttp(IServiceLocator $serviceLocator): GameTurnHttp
    {
        return new GameTurnHttp(
            (new GameStrikePorts($serviceLocator))->userAccess(),
            $this->turns($serviceLocator),
        );
    }

    /**
     * HTTP поддержания и прекращения заклинания.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameActiveSpellHttp Сценарий.
     */
    public function createActiveSpellHttp(IServiceLocator $serviceLocator): GameActiveSpellHttp
    {
        return new GameActiveSpellHttp(
            (new GameStrikePorts($serviceLocator))->userAccess(),
            $this->turns($serviceLocator),
            new GameActiveSpellBody(),
        );
    }

    /**
     * HTTP чтения активных заклинаний.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameActiveSpellQueryHttp Сценарий.
     */
    public function createActiveSpellQueryHttp(IServiceLocator $serviceLocator): GameActiveSpellQueryHttp
    {
        $ports = new GameStrikePorts($serviceLocator);
        $gateway = $ports->gateway();

        return new GameActiveSpellQueryHttp(
            $ports->userAccess(),
            $ports->games(),
            (new GameStrikeCardWiring())->card($serviceLocator),
            new GameSessionRepository($gateway),
            new GameActiveSpellRepository($gateway),
            new GameActiveSpellView(),
        );
    }

    /**
     * HTTP чтения длительных эффектов.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameActiveEffectQueryHttp Сценарий.
     */
    public function createActiveEffectQueryHttp(IServiceLocator $serviceLocator): GameActiveEffectQueryHttp
    {
        $ports = new GameStrikePorts($serviceLocator);
        $gateway = $ports->gateway();

        return new GameActiveEffectQueryHttp(
            $ports->userAccess(),
            $ports->games(),
            (new GameStrikeCardWiring())->card($serviceLocator),
            new GameSessionRepository($gateway),
            new GameActiveEffectRepository($gateway),
            new GameActiveEffectView(),
        );
    }

    /**
     * Доменная команда карточки действия.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameActionDeclare Команда.
     */
    private function actionDomain(IServiceLocator $serviceLocator): GameActionDeclare
    {
        $ports = new GameStrikePorts($serviceLocator);
        $gateway = $ports->gateway();
        $card = (new GameStrikeCardWiring())->card($serviceLocator);
        (new GameStrikeCardWiring())->delivery($serviceLocator, $card);
        $rules = (new GameStrikeRuleWiring($serviceLocator))->rules();
        $write = new GameStrikeWriteWiring($serviceLocator);
        $ruleWiring = new GameStrikeRuleWiring($serviceLocator);
        $sheets = $write->sheets($rules);

        return new GameActionDeclare(
            $gateway,
            $ports->games(),
            $card,
            $write->perform($rules),
            new GamePreparedDefense(),
            new GameActionChat($ports->chats(), new GameStrikeChat($this->npcs($gateway), $rules)),
            $sheets,
            $ports->events(),
            $write->processes($rules),
            $rules,
            new GameActionManeuver($rules, $sheets, $write->writes(), $ruleWiring->checks()),
        );
    }

    /**
     * Сценарий конца хода.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameTurns Сценарий.
     */
    private function turns(IServiceLocator $serviceLocator): GameTurns
    {
        $gateway = (new GameStrikePorts($serviceLocator))->gateway();
        $rules = (new GameStrikeRuleWiring($serviceLocator))->rules();
        $write = new GameStrikeWriteWiring($serviceLocator);
        $access = $this->turnAccess($serviceLocator, $gateway);
        $battles = new GameBattleRepository($gateway);
        $cursor = new GameTurnCursor();
        $sheets = $write->sheets($rules);
        $snapshot = new GameTurnSnapshot($rules, $this->npcs($gateway), $sheets);
        $ports = new GameStrikePorts($serviceLocator);
        $shared = new GameStrikeShared(
            $gateway,
            $ports->games(),
            $ports->mutations(),
            $rules,
            (new GameStrikeCardWiring())->card($serviceLocator),
            $ports->events(),
        );
        $active = new GameActiveSpellWiring($serviceLocator, $rules, $shared, $access);
        $effects = new GameActiveEffectWiring($serviceLocator, $shared);
        $spellChat = new GameSpellCastChat($ports->chats(), $shared->chat);

        return new GameTurns(
            $this->turnEnvelope($serviceLocator, $gateway, $access),
            new GameTurnScenario(new GameBattleLookup($battles), $access, $cursor),
            $this->turnEnd($serviceLocator, $rules, $write, $gateway, $access, $snapshot, $battles, $cursor, $active->turnStart, $effects->tick),
            $this->turnContinue($serviceLocator, $rules, $write, $snapshot, $battles, $spellChat),
            new GameTurnAbort($battles, $spellChat),
            $this->turnProcessAbort($serviceLocator, $rules, $write, $gateway, $access, $battles),
            $active->sustain,
            $active->drop,
        );
    }

    /**
     * Проверки доступа хода.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param ISmartTableGateway $gateway Шлюз.
     *
     * @return GameTurnAccess Проверки.
     */
    private function turnAccess(IServiceLocator $serviceLocator, ISmartTableGateway $gateway): GameTurnAccess
    {
        return new GameTurnAccess(
            (new GameStrikePorts($serviceLocator))->games(),
            (new GameStrikeCardWiring())->card($serviceLocator),
            new GameSessionRepository($gateway),
            new GameCharacterRepository($gateway),
            new GameMemberRepository($gateway),
        );
    }

    /**
     * Конверт команды хода.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param ISmartTableGateway $gateway Шлюз.
     * @param GameTurnAccess $access Проверки.
     *
     * @return GameTurnEnvelope Конверт.
     */
    private function turnEnvelope(
        IServiceLocator $serviceLocator,
        ISmartTableGateway $gateway,
        GameTurnAccess $access,
    ): GameTurnEnvelope {
        return new GameTurnEnvelope(
            $access,
            new GameCommandRepository($gateway),
            new GameReplayTransaction($gateway),
            new GameDeliverySignal((new GameStrikePorts($serviceLocator))->events()),
        );
    }

    /**
     * Сценарий конца хода.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeWriteWiring $write Запись.
     * @param ISmartTableGateway $gateway Шлюз.
     * @param GameTurnAccess $access Проверки.
     * @param GameTurnSnapshot $snapshot Снимок.
     * @param GameBattleRepository $battles Бои.
     * @param GameTurnCursor $cursor Курсор.
     * @param GameActiveSpellTurnStart $activeSpells Вопрос поддержания.
     * @param GameActiveEffectTick $activeEffects Тик длительных эффектов.
     *
     * @return GameTurnEnd Сценарий.
     */
    private function turnEnd(
        IServiceLocator $serviceLocator,
        GameStrikeRules $rules,
        GameStrikeWriteWiring $write,
        ISmartTableGateway $gateway,
        GameTurnAccess $access,
        GameTurnSnapshot $snapshot,
        GameBattleRepository $battles,
        GameTurnCursor $cursor,
        GameActiveSpellTurnStart $activeSpells,
        GameActiveEffectTick $activeEffects,
    ): GameTurnEnd {
        $chats = (new GameStrikePorts($serviceLocator))->chats();
        $announce = new GameTurnAnnounce(
            new GameCombatNotices($chats),
            new GameActionChat($chats, new GameStrikeChat($this->npcs($gateway), $rules)),
            $chats,
        );

        return new GameTurnEnd(
            new GameTurnWait(new GameActionRepository($gateway), $rules, new GameActionCards(), $write->perform($rules)),
            new GameTurnEndSheet($snapshot, $this->ticks($serviceLocator, $rules), $write->writes()),
            $battles,
            new GameBattleMarks(),
            $cursor,
            $announce,
            $activeSpells,
            $activeEffects,
        );
    }

    /**
     * Сценарий доплаты долга.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeWriteWiring $write Запись.
     * @param GameTurnSnapshot $snapshot Снимок.
     * @param GameBattleRepository $battles Бои.
     * @param GameSpellCastChat $chat Строки сотворения в долг.
     *
     * @return GameTurnContinue Сценарий.
     */
    private function turnContinue(
        IServiceLocator $serviceLocator,
        GameStrikeRules $rules,
        GameStrikeWriteWiring $write,
        GameTurnSnapshot $snapshot,
        GameBattleRepository $battles,
        GameSpellCastChat $chat,
    ): GameTurnContinue {
        return new GameTurnContinue(
            $snapshot,
            $write->writes(),
            $battles,
            $this->actionDomain($serviceLocator),
            (new GameWoundPortFactory())->createActions($serviceLocator),
            $write->open($rules),
            $this->spellCast($serviceLocator),
            $chat,
        );
    }

    /**
     * Сценарий прерывания процесса.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeWriteWiring $write Запись.
     * @param ISmartTableGateway $gateway Шлюз.
     * @param GameTurnAccess $access Проверки.
     * @param GameBattleRepository $battles Бои.
     *
     * @return GameTurnProcessAbort Сценарий.
     */
    private function turnProcessAbort(
        IServiceLocator $serviceLocator,
        GameStrikeRules $rules,
        GameStrikeWriteWiring $write,
        ISmartTableGateway $gateway,
        GameTurnAccess $access,
        GameBattleRepository $battles,
    ): GameTurnProcessAbort {
        return new GameTurnProcessAbort(
            $access,
            $battles,
            new GameActionRepository($gateway),
            $write->processes($rules),
            new GameProcessRepository($gateway),
        );
    }

    /**
     * Тики конца хода.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameTurnTicks Тики.
     */
    private function ticks(IServiceLocator $serviceLocator, GameStrikeRules $rules): GameTurnTicks
    {
        $rulesWiring = new GameStrikeRuleWiring($serviceLocator);
        $mechanics = (new GameStrikeMechanicPorts($serviceLocator))->mechanics();
        $consequences = $rulesWiring->consequences($rules);
        $states = new GameTurnSheetStates();

        return new GameTurnTicks(
            new GameTurnTickWound(
                $rulesWiring->checkRoll(),
                new GameMechanicResolver($mechanics, (new GameStrikeMechanicPorts($serviceLocator))->engine()),
                $states,
            ),
            new GameTurnTickBlood($consequences, $states),
            new GameTurnTickDot(
                new GameStrikeSplits(),
                (new GameStrikeCharacterPorts($serviceLocator))->formulaContexts(),
                $consequences,
                $states,
            ),
            $states,
        );
    }

    /**
     * Строки NPC.
     *
     * @param ISmartTableGateway $gateway Шлюз.
     *
     * @return GameNpcRepository Репозиторий.
     */
    private function npcs(ISmartTableGateway $gateway): GameNpcRepository
    {
        return new GameNpcRepository($gateway);
    }
}
