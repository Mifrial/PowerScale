<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SelfDamageOutcomeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SkipAfterActionOutcomeEffect;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Последствия модификатора действия: пропуск и урон по себе.
 */
final class GameActionModifierOutcome
{
    /**
     * Создаёт исполнитель последствий.
     *
     * @param GameActionModifierPorts $ports Правила, листы и запись.
     *
     * @return void
     */
    public function __construct(private readonly GameActionModifierPorts $ports)
    {
    }

    /**
     * Применяет последствия к листу атакующего.
     *
     * @param GameActionModifierInput $input Снимок удара.
     * @param ActionModifierEffect $effect Модификатор.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка или лист битые.
     */
    public function apply(GameActionModifierInput $input, ActionModifierEffect $effect, array &$result): void
    {
        foreach ($effect->getEffects() as $outcome) {
            if ($outcome instanceof SkipAfterActionOutcomeEffect) {
                $result['skipped'] = true;
                continue;
            }

            $this->applySelfDamage($input, $outcome, $result);
        }
    }

    /**
     * Урон по себе одного последствия.
     *
     * @param GameActionModifierInput $input Снимок удара.
     * @param mixed $outcome Последствие.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     *
     * @throws GameInvalidException Если исход или лист битые.
     */
    private function applySelfDamage(GameActionModifierInput $input, mixed $outcome, array &$result): void
    {
        if (!$outcome instanceof SelfDamageOutcomeEffect) {
            throw new GameInvalidException('Game action modifier outcome is invalid');
        }

        $injury = $this->injuryOf($input, $outcome);
        $prepared = $this->ports->resolution->damage->selfDamage(
            $input->game,
            $input->attackerKind,
            $input->attackerId,
            $injury,
            $outcome->getDamageTypeCode(),
        );
        $this->store($input, $prepared, $injury, $result);
    }

    /**
     * Пишет лист и итог урона по себе.
     *
     * @param GameActionModifierInput $input Снимок удара.
     * @param array<string, mixed> $prepared Подготовка записи.
     * @param DimensionalNumber $injury Урон.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    private function store(
        GameActionModifierInput $input,
        array $prepared,
        DimensionalNumber $injury,
        array &$result,
    ): void {
        $result['sheetVersion'] = $this->ports->sheetWrites->write(
            $input->game,
            $input->attackerKind,
            $input->attackerId,
            $prepared['actualVersion'],
            $prepared['operations'],
        );
        $result['injury'] = $this->ports->amounts->view($injury);
        $result['consequence'] = $prepared['consequence'];
    }

    /**
     * Урон по себе: сила удара, сдвинутая на размер.
     *
     * @param GameActionModifierInput $input Снимок удара.
     * @param SelfDamageOutcomeEffect $effect Последствие.
     *
     * @return DimensionalNumber Повреждение.
     *
     * @throws GameInvalidException Если профиль или лист битые.
     */
    private function injuryOf(GameActionModifierInput $input, SelfDamageOutcomeEffect $effect): DimensionalNumber
    {
        $target = $this->target($input, $this->profile($input->open));
        $number = $this->ports->rules->damage->strikeStrength($target);
        $shift = $effect->getSizeDelta();

        return $shift === 0 ? $number : $number->modify($shift * CharacteristicNumber::BASE_STEP);
    }

    /**
     * Профиль оружия открытой строки.
     *
     * @param array<string, mixed> $open Открытая строка удара.
     *
     * @return array{itemRuleCode: string, profileType: string, profileIndex: int, itemInventoryId: int} Профиль.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function profile(array $open): array
    {
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        $itemInventoryId = $open['item_inventory_id'] ?? null;
        if (
            !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !is_int($itemInventoryId)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return [
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
            'profileIndex' => $profileIndex,
            'itemInventoryId' => $itemInventoryId,
        ];
    }

    /**
     * Цель удара по профилю.
     *
     * @param GameActionModifierInput $input Снимок удара.
     * @param array{itemRuleCode: string, profileType: string, profileIndex: int, itemInventoryId: int} $profile Профиль.
     *
     * @return GameStrikeTarget Цель.
     */
    private function target(GameActionModifierInput $input, array $profile): GameStrikeTarget
    {
        $npc = $input->attackerKind === 'npc';
        $gameId = $input->game->getId();

        return GameStrikeTarget::of(
            $input->game->getSpaceId(),
            $input->game->getRulesRevision(),
            $profile['itemRuleCode'],
            $profile['profileType'],
            $profile['profileIndex'],
            $profile['itemInventoryId'],
            $input->attackerKind,
            $input->attackerId,
            $npc ? $this->ports->sheets->getNpcSheet($gameId, $input->attackerId) : null,
            $npc ? $this->ports->sheets->getNpcChoices($gameId, $input->attackerId) : null,
            $npc ? $this->ports->sheets->getNpcVersion($gameId, $input->attackerId) : null,
            $input->actionCharacteristicModifier,
            0,
            $input->gripBonus,
        );
    }
}
