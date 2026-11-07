<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

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

        return new GameRunActionHttp(
            $ports->userAccess(),
            new GameRunAction(
                new GameRunActionBody(),
                $domain->create($serviceLocator),
                $domain->createWide($serviceLocator),
                $this->actionDomain($serviceLocator),
                (new GameWoundPortFactory())->createActions($serviceLocator),
                new GameActionRepository($gateway),
                new GameCommandRepository($gateway),
                $ports->games(),
                (new GameStrikeRuleWiring($serviceLocator))->rules(),
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
        $ports = new GameStrikePorts($serviceLocator);
        $rules = (new GameStrikeRuleWiring($serviceLocator))->rules();
        $write = new GameStrikeWriteWiring($serviceLocator);
        $chats = $ports->chats();

        return new GameTurns(
            $ports->gateway(),
            $ports->games(),
            (new GameStrikeCardWiring())->card($serviceLocator),
            $rules,
            $ports->mutations(),
            $chats,
            $this->ticks($serviceLocator, $rules),
            $write->open($rules),
            $ports->events(),
            new GameCombatNotices($chats),
            $write->availability($rules),
            (new GameStrikeRuleWiring($serviceLocator))->checks(),
            $this->actionDomain($serviceLocator),
            $write->processes($rules),
            (new GameWoundPortFactory())->createActions($serviceLocator),
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

        return new GameTurnTicks(
            $rulesWiring->checkRoll(),
            $rulesWiring->consequences($rules),
            new GameStrikeSplits(),
            (new GameStrikeCharacterPorts($serviceLocator))->formulaContexts(),
            (new GameStrikeMechanicPorts($serviceLocator))->mechanics(),
            (new GameStrikeMechanicPorts($serviceLocator))->engine(),
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
