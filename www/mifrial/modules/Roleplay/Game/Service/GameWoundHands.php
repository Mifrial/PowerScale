<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;

/**
 * Свободные руки держателя: раса, надетое и уже зажатые раны.
 */
final class GameWoundHands
{
    /**
     * Создаёт расчёт рук.
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
     * Свободные руки держателя.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>} $actorSheet Лист.
     * @param array{kind: string, id: int} $actor Держатель.
     * @param GameRecord $game Игра.
     *
     * @return int Число.
     */
    public function freeHands(CharacterRuleSlice $slice, array $actorSheet, array $actor, GameRecord $game): int
    {
        return $this->handCount()
            - $this->equippedHands($slice, $actorSheet['choices'])
            - $this->heldWounds($actor, $game);
    }

    /**
     * Число рук расы. У всех текущих рас — 2.
     *
     * @return int Число.
     */
    private function handCount(): int
    {
        return 2;
    }

    /**
     * Сумма min занятых рук надетого.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return int Число.
     */
    private function equippedHands(CharacterRuleSlice $slice, array $choices): int
    {
        $occupied = 0;
        $inventory = $choices['inventory'] ?? [];
        if (!is_array($inventory)) {
            return 0;
        }

        foreach ($inventory as $row) {
            if (!is_array($row) || ($row['equipped'] ?? false) !== true || !is_string($row['ruleCode'] ?? null)) {
                continue;
            }

            $spec = $slice->findLive($row['ruleCode'])?->getSpec();
            $hands = $spec instanceof ItemSpec ? $spec->getOccupyHands() : null;
            $occupied += $hands === null ? 0 : $hands->getMin();
        }

        return $occupied;
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
