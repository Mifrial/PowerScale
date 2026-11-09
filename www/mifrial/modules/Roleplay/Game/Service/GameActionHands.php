<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\CharacterHands;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithComponents;

/**
 * Руки актора в игре: свободные руки, зажатые раны и проверка требования.
 */
final class GameActionHands
{
    /**
     * Создаёт игровой расчёт рук.
     *
     * @param GameStrikeRules $rules Срез.
     * @param GameCharacterRepository $characters Персонажи игры.
     * @param GameNpcRepository $npcs NPC игры.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameCharacterRepository $characters,
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Руки актора с учётом зажатых ран.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param array{kind: string, id: int} $actor Держатель.
     *
     * @return CharacterHands Руки.
     */
    public function build(GameRecord $game, CharacterRuleSlice $slice, array $choices, array $actor): CharacterHands
    {
        return new CharacterHands($slice, $choices, $this->heldWounds($actor, $game));
    }

    /**
     * Проверяет, что у актора хватает свободных рук на действие.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param array{kind: string, id: int} $actor Держатель.
     * @param AbilityWithComponents $spec Карточка действия или заклинания.
     *
     * @return void
     *
     * @throws GameInvalidException Если рук не хватает.
     */
    public function assertAvailable(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $choices,
        array $actor,
        AbilityWithComponents $spec,
    ): void {
        $required = CharacterHands::getRequiredHandsOf($spec);
        if ($required === 0) {
            return;
        }

        $hands = $this->build($game, $slice, $choices, $actor);
        if ($required > $hands->getFreeHands()) {
            throw new GameInvalidException(sprintf(
                'Game action requires %d free hands, %d available',
                $required,
                $hands->getFreeHands(),
            ));
        }
    }

    /**
     * Внешние раны, которые этот держатель уже держит.
     *
     * @param array{kind: string, id: int} $actor Держатель.
     * @param GameRecord $game Игра.
     *
     * @return int Число.
     */
    private function heldWounds(array $actor, GameRecord $game): int
    {
        $held = 0;
        foreach ($this->characters->getListByGame($game->getId(), null) as $membership) {
            $sheet = $this->rules->data->characterSnapshot($membership->getCharacterId())['sheet'];
            $held += $this->holdsOn($sheet, $actor);
        }

        foreach ($this->npcs->getListByGame($game->getId()) as $npc) {
            $sheet = $npc->getVersion()['sheet'] ?? [];
            $held += is_array($sheet) ? $this->holdsOn($sheet, $actor) : 0;
        }

        return $held;
    }

    /**
     * Зажимы одного листа.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param array{kind: string, id: int} $actor Держатель.
     *
     * @return int Число.
     */
    private function holdsOn(array $sheet, array $actor): int
    {
        $held = 0;
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return 0;
        }

        foreach ($states as $row) {
            $holder = is_array($row) ? ($row['heldBy'] ?? null) : null;
            $external = is_array($row) && ($row['internal'] ?? false) !== true;
            if ($external && $holder === $actor) {
                $held++;
            }
        }

        return $held;
    }
}
