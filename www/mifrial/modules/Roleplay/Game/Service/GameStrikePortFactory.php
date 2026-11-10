<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterKeywordExperience;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;

/**
 * Сборка доменных портов удара.
 */
final class GameStrikePortFactory
{
    /**
     * Создаёт порт одиночного удара.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGameStrikes Фасад.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function create(IServiceLocator $serviceLocator): IGameStrikes
    {
        $ports = new GameStrikePorts($serviceLocator);
        $cardWiring = new GameStrikeCardWiring();
        $card = $cardWiring->card($serviceLocator);
        $cardWiring->delivery($serviceLocator, $card);
        $rules = (new GameStrikeRuleWiring($serviceLocator))->rules();

        return $this->composeStrikes($serviceLocator, $ports, $card, $rules);
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
        $ports = new GameStrikePorts($serviceLocator);
        $cardWiring = new GameStrikeCardWiring();
        $card = $cardWiring->card($serviceLocator);
        $cardWiring->delivery($serviceLocator, $card);
        $rulesWiring = new GameStrikeRuleWiring($serviceLocator);
        $rules = $rulesWiring->rules();
        $write = new GameStrikeWriteWiring($serviceLocator);

        return (new GameWideFactory())->create(
            $ports->gateway(),
            $ports->games(),
            $ports->mutations(),
            $rules,
            $card,
            $ports->events(),
            $rulesWiring->consequences($rules),
            $write->availability($rules),
            $rulesWiring->checks(),
            $write->processes($rules),
        );
    }

    /**
     * Сессия процесса для проекций.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameProcessFlow Сценарий.
     *
     * @throws KernelException Если нет шлюза или порта.
     */
    public function createProcessFlow(IServiceLocator $serviceLocator): GameProcessFlow
    {
        $rules = (new GameStrikeRuleWiring($serviceLocator))->rules();

        return (new GameStrikeWriteWiring($serviceLocator))->processes($rules);
    }

    /**
     * Снимки листов удара: уровни способностей и листы целей.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameStrikeSheets Снимки.
     *
     * @throws KernelException Если нет порта.
     */
    public function createSheets(IServiceLocator $serviceLocator): GameStrikeSheets
    {
        $rules = (new GameStrikeRuleWiring($serviceLocator))->rules();

        return (new GameStrikeWriteWiring($serviceLocator))->sheets($rules);
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
        return (new GameStrikeRuleWiring($serviceLocator))->rules();
    }

    /**
     * Проверки прироста состояний: кубик и связка из спеки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameStateIncreaseChecks Исполнитель.
     *
     * @throws KernelException Если порта нет.
     */
    public function createStateIncreaseChecks(IServiceLocator $serviceLocator): GameStateIncreaseChecks
    {
        return (new GameStrikeRuleWiring($serviceLocator))->checks();
    }

    /**
     * Собирает роли одиночного удара.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     * @param GameStrikePorts $ports Порты.
     * @param GameCardAccess $card Фильтр карточки.
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameStrikes Фасад.
     */
    private function composeStrikes(
        IServiceLocator $serviceLocator,
        GameStrikePorts $ports,
        GameCardAccess $card,
        GameStrikeRules $rules,
    ): GameStrikes {
        $rulesWiring = new GameStrikeRuleWiring($serviceLocator);
        $write = new GameStrikeWriteWiring($serviceLocator);
        $processes = $write->processes($rules);
        $consequences = $rulesWiring->consequences($rules);
        $shared = $this->shared($ports, $card, $rules);
        $characters = new GameStrikeCharacterPorts($serviceLocator);
        $magicResistance = new GameSpellMagicResistance($rules->defense);
        $spellFacts = new GameSpellTouchFacts(
            new GameSpellCastFacts(
                $rules,
                $shared->sheets,
                $characters->characterSheets(),
                $write->availability($rules),
                new GameActionResourceResolver(),
                new GameActionHands($rules, $shared->repos->characters, $shared->repos->npcs),
            ),
            $shared->sheets,
        );
        $spellTouch = new GameSpellTouchFinish(
            $spellFacts,
            new GameSpellCastCheck(
                $rulesWiring->checkRoll(),
                new GameCheckChain(),
                $magicResistance,
            ),
            new GameSpellCastDamage(
                $rules,
                new CharacterKeywordExperience(),
                new GameStrikeAmounts(),
                $magicResistance,
                $consequences,
            ),
            $shared->sheetWrites,
            new GameSpellCastChat($ports->chats(), $shared->chat),
        );
        $close = (new GameStrikeCloseFactory())->create(
            $rules,
            $shared,
            $consequences,
            $rulesWiring->checks(),
            $processes,
            $spellTouch,
        );
        $roles = new GameStrikeRoleFactory($rules, $shared);
        $body = new GameStrikeBody();

        return new GameStrikes(
            $roles->declare($body, $consequences, $write->availability($rules), $processes, $spellFacts),
            $roles->window($rulesWiring->checkRoll(), $close),
            $roles->closeRunner($body, $close),
        );
    }

    /**
     * Общие сервисы ударного домена.
     *
     * @param GameStrikePorts $ports Порты.
     * @param GameCardAccess $card Фильтр карточки.
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameStrikeShared Общие сервисы.
     */
    private function shared(GameStrikePorts $ports, GameCardAccess $card, GameStrikeRules $rules): GameStrikeShared
    {
        return new GameStrikeShared(
            $ports->gateway(),
            $ports->games(),
            $ports->mutations(),
            $rules,
            $card,
            $ports->events(),
        );
    }
}
