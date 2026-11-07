<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
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
                new GameRunActionDeclare($this->actionDomain($serviceLocator), $wounds),
                new GameRunActionStrikeDeclare($strikes, $wideStrikes, $ports->games(), $rules),
                new GameRunActionRespond($strikes, $wideStrikes, $repository, $commands),
            ),
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

        return new GameActionDeclare(
            $gateway,
            $ports->games(),
            $card,
            $write->perform($rules),
            new GamePreparedDefense(),
            new GameActionChat($ports->chats(), new GameStrikeChat($this->npcs($gateway), $rules)),
            $write->sheets($rules),
            $ports->events(),
            $write->processes($rules),
            $rules,
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
        $snapshot = new GameTurnSnapshot($rules, $this->npcs($gateway));

        return new GameTurns(
            $this->turnEnvelope($serviceLocator, $gateway, $access),
            new GameTurnScenario(new GameBattleLookup($battles), $access, $cursor),
            $this->turnEnd($serviceLocator, $rules, $write, $gateway, $access, $snapshot, $battles, $cursor),
            $this->turnContinue($serviceLocator, $rules, $write, $snapshot, $battles),
            new GameTurnAbort($battles),
            $this->turnProcessAbort($serviceLocator, $rules, $write, $gateway, $access, $battles),
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
     *
     * @return GameTurnContinue Сценарий.
     */
    private function turnContinue(
        IServiceLocator $serviceLocator,
        GameStrikeRules $rules,
        GameStrikeWriteWiring $write,
        GameTurnSnapshot $snapshot,
        GameBattleRepository $battles,
    ): GameTurnContinue {
        return new GameTurnContinue(
            $snapshot,
            $write->writes(),
            $battles,
            $this->actionDomain($serviceLocator),
            (new GameWoundPortFactory())->createActions($serviceLocator),
            $write->open($rules),
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
