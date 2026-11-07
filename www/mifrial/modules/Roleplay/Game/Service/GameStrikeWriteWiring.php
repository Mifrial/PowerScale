<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionMask;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterRequirementFacts;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Rule\Service\AbilityRequirementCheck;

/**
 * Сборка листов, процессов и записи открытого удара.
 */
final class GameStrikeWriteWiring
{
    private readonly GameStrikePorts $ports;

    private readonly GameStrikeCharacterPorts $characters;

    private readonly GameStrikeRuleWiring $rules;

    /**
     * Создаёт сборку сервисов записи.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return void
     */
    public function __construct(IServiceLocator $serviceLocator)
    {
        $this->ports = new GameStrikePorts($serviceLocator);
        $this->characters = new GameStrikeCharacterPorts($serviceLocator);
        $this->rules = new GameStrikeRuleWiring($serviceLocator);
    }

    /**
     * Запись листа.
     *
     * @return GameStrikeSheetWrites Запись.
     */
    public function writes(): GameStrikeSheetWrites
    {
        return new GameStrikeSheetWrites($this->ports->mutations(), $this->npcs());
    }

    /**
     * Снимки листов удара.
     *
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameStrikeSheets Снимки.
     */
    public function sheets(GameStrikeRules $rules): GameStrikeSheets
    {
        $gateway = $this->ports->gateway();

        return new GameStrikeSheets(
            $rules,
            $this->npcs(),
            new GameCharacterRepository($gateway),
            new GameMemberRepository($gateway),
            $this->projection(),
        );
    }

    /**
     * Гейт принадлежности и требований карточки действия.
     *
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameCardAvailability Сервис.
     */
    public function availability(GameStrikeRules $rules): GameCardAvailability
    {
        return new GameCardAvailability(
            $rules,
            $this->sheets($rules),
            $this->characters->characterSheets(),
            new CharacterRequirementFacts(),
            new AbilityRequirementCheck(),
        );
    }

    /**
     * Сессия процесса.
     *
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameProcessFlow Сценарий.
     */
    public function processes(GameStrikeRules $rules): GameProcessFlow
    {
        return new GameProcessFlow(
            new GameProcessRepository($this->ports->gateway()),
            new GameProcessRules(),
            $rules,
            $this->availability($rules),
            new GamePendingActionCosts(),
        );
    }

    /**
     * Открытие удара.
     *
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameStrikeOpen Сервис открытия.
     */
    public function open(GameStrikeRules $rules): GameStrikeOpen
    {
        $gateway = $this->ports->gateway();
        $writes = $this->writes();

        return (new GameStrikeOpenFactory())->create(
            $rules,
            new GameBattleRepository($gateway),
            new GameActionRepository($gateway),
            new GameStrikeSpend($rules, $writes),
            $this->sheets($rules),
            $writes,
            $this->processes($rules),
        );
    }

    /**
     * Исполнение карточки действия.
     *
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameActionPerform Исполнение.
     */
    public function perform(GameStrikeRules $rules): GameActionPerform
    {
        $ports = new GameActionPerformPorts(
            $rules,
            $this->npcs(),
            $this->writes(),
            new GameActionCards(),
            new GamePendingActionCosts(),
            $this->availability($rules),
            $this->rules->checks(),
        );

        return new GameActionPerform(
            $rules,
            $ports->cards,
            $ports->sheetWrites,
            new GameActionPerformSteps(
                $ports,
                new GameActionPerformDocument($ports),
                new GameActionPerformPrice($ports),
            ),
        );
    }

    /**
     * Строки NPC.
     *
     * @return GameNpcRepository Репозиторий.
     */
    private function npcs(): GameNpcRepository
    {
        return new GameNpcRepository($this->ports->gateway());
    }

    /**
     * Проекция листа конфликта.
     *
     * @return ConflictSheetProjection Проекция.
     */
    private function projection(): ConflictSheetProjection
    {
        return new ConflictSheetProjection(
            new CharacterSectionMask(),
            new GameCharacterProjectionMask(),
            new GameNpcVisibility(),
        );
    }
}
