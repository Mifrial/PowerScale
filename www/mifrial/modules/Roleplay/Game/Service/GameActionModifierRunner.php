<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SelfDamageOutcomeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SkipAfterActionOutcomeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ModifierTrigger;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Исполнение модификаторов действия по их данным: момент, проверка, последствия.
 */
final class GameActionModifierRunner
{
    /**
     * Создаёт исполнитель.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeResolution $resolution Последствия урона.
     * @param GameStrikeConsequences $consequences Бросок и чат.
     * @param GameStrikeChat $chat Подписи участников.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameActionModifiers $modifiers Модификаторы карточки.
     * @param GameActionModifierPresenter $presenter Подписи.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameActionModifiers $modifiers,
        private readonly GameActionModifierPresenter $presenter,
    ) {
    }

    /**
     * Выполняет объявленные модификаторы.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытая строка удара.
     * @param int $strikeId Удар.
     * @param string $actionRuleCode Карточка действия.
     * @param string $attackerKind Вид участника: character или npc.
     * @param int $attackerId Атакующий.
     * @param array<int, string> $codes Объявленные коды.
     * @param int $actionCharacteristicModifier Сдвиг характеристики действия.
     * @param int $gripBonus Бонус удержания.
     * @param int $actorUserId Актор чата.
     *
     * @return array<string, mixed> Итог: коды, бросок, успех, урон, пропуск помехи.
     *
     * @throws GameInvalidException Если код, момент, лист или запись битые.
     */
    public function perform(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $strikeId,
        string $actionRuleCode,
        string $attackerKind,
        int $attackerId,
        array $codes,
        int $actionCharacteristicModifier,
        int $gripBonus,
        int $actorUserId,
    ): array {
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $levels = $this->sheets->abilityLevels($game, $attackerKind, $attackerId);

        $threadId = 'attack-' . $strikeId;
        $speaker = $this->chat->getSpeakerOf($attackerKind, $attackerId);
        $parentId = GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $attackerName = $this->chat->getEntityName($attackerKind, $attackerId);

        $result = ['codes' => [], 'roll' => null, 'success' => false, 'skipped' => false];
        foreach ($codes as $code) {
            $modifier = $this->modifiers->find($slice, $actionRuleCode, $levels, $code);
            $effect = $modifier['effect'];
            if (!ModifierTrigger::supports($effect->getTrigger())) {
                throw new GameInvalidException('Game action modifier trigger is invalid');
            }

            $sheet = $this->sheets->snapshot($game, $attackerKind, $attackerId)['sheet']['sheet'] ?? null;
            if (!is_array($sheet)) {
                throw new GameInvalidException('Game strike sheet is invalid');
            }

            $roll = $this->rollOf($game, $sheet, $effect);
            $success = $roll === null || ($roll['success'] ?? false) === true;
            if ($roll !== null) {
                $this->consequences->postRolls($game, $actorUserId, $threadId, [$roll], $speaker, $parentId);
            }

            $this->consequences->postLines(
                $game,
                $actorUserId,
                $threadId,
                [$this->outcomeLine($slice, $attackerName, $modifier['name'], $effect, $roll !== null, $success)],
                $speaker,
                $parentId,
            );

            $result['codes'][] = $modifier['code'];
            $result['roll'] = $roll;
            $result['success'] = $success;
            if (!$success) {
                continue;
            }

            $this->apply(
                $game,
                $open,
                $attackerKind,
                $attackerId,
                $effect,
                $actionCharacteristicModifier,
                $gripBonus,
                $result,
            );
        }

        return $result;
    }

    /**
     * Бросок модификатора, если проверка задана.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param ActionModifierEffect $effect Модификатор.
     *
     * @return array<string, mixed>|null Бросок или null.
     */
    private function rollOf(GameRecord $game, array $sheet, ActionModifierEffect $effect): ?array
    {
        $check = $effect->getCheck();
        if ($check === null) {
            return null;
        }

        return $this->consequences->optionalCheck($game, $sheet, $check->getCode(), $check->getDifficulty());
    }

    /**
     * Применяет последствия к листу атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытая строка удара.
     * @param string $attackerKind Вид участника.
     * @param int $attackerId Атакующий.
     * @param ActionModifierEffect $effect Модификатор.
     * @param int $actionCharacteristicModifier Сдвиг характеристики действия.
     * @param int $gripBonus Бонус удержания.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка или лист битые.
     */
    private function apply(
        GameRecord $game,
        array $open,
        string $attackerKind,
        int $attackerId,
        ActionModifierEffect $effect,
        int $actionCharacteristicModifier,
        int $gripBonus,
        array &$result,
    ): void {
        foreach ($effect->getEffects() as $outcome) {
            if ($outcome instanceof SkipAfterActionOutcomeEffect) {
                $result['skipped'] = true;
                continue;
            }

            if (!$outcome instanceof SelfDamageOutcomeEffect) {
                throw new GameInvalidException('Game action modifier outcome is invalid');
            }

            $injury = $this->injuryOf(
                $game,
                $open,
                $attackerKind,
                $attackerId,
                $outcome,
                $actionCharacteristicModifier,
                $gripBonus,
            );
            $prepared = $this->resolution->selfDamage(
                $game,
                $attackerKind,
                $attackerId,
                $injury,
                $outcome->getDamageTypeCode(),
            );
            $result['sheetVersion'] = $this->sheetWrites->write(
                $game,
                $attackerKind,
                $attackerId,
                $prepared['actualVersion'],
                $prepared['operations'],
            );
            $result['injury'] = $this->amounts->view($injury);
            $result['consequence'] = $prepared['consequence'];
        }
    }

    /**
     * Урон по себе: сила удара, сдвинутая на размер.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытая строка удара.
     * @param string $attackerKind Вид участника: character или npc.
     * @param int $attackerId Атакующий.
     * @param SelfDamageOutcomeEffect $effect Последствие.
     * @param int $actionCharacteristicModifier Сдвиг характеристики действия.
     * @param int $gripBonus Бонус удержания.
     *
     * @return DimensionalNumber Повреждение.
     *
     * @throws GameInvalidException Если профиль или лист битые.
     */
    private function injuryOf(
        GameRecord $game,
        array $open,
        string $attackerKind,
        int $attackerId,
        SelfDamageOutcomeEffect $effect,
        int $actionCharacteristicModifier,
        int $gripBonus,
    ): DimensionalNumber {
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

        $npc = $attackerKind === 'npc';
        $strength = $this->rules->strikeStrength(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $attackerKind,
            $attackerId,
            $npc ? $this->sheets->getNpcSheet($game->getId(), $attackerId) : null,
            $npc ? $this->sheets->getNpcChoices($game->getId(), $attackerId) : null,
            $npc ? $this->sheets->getNpcVersion($game->getId(), $attackerId) : null,
            $actionCharacteristicModifier,
            0,
            $gripBonus,
        );
        $number = $strength instanceof CharacteristicNumber
            ? $strength
            : new CharacteristicNumber($strength->getBase(), $strength->getSize());
        $shift = $effect->getSizeDelta();

        return $shift === 0 ? $number : $number->modify($shift * CharacteristicNumber::BASE_STEP);
    }

    /**
     * Строка исхода в чат.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $attackerName Имя атакующего.
     * @param string $modifierName Название модификатора.
     * @param ActionModifierEffect $effect Модификатор.
     * @param bool $checked Была ли проверка.
     * @param bool $success Успех.
     *
     * @return string Текст.
     */
    private function outcomeLine(
        CharacterRuleSlice $slice,
        string $attackerName,
        string $modifierName,
        ActionModifierEffect $effect,
        bool $checked,
        bool $success,
    ): string {
        if (!$success) {
            return sprintf('%s: %s — провал, проверка не пройдена.', $attackerName, $modifierName);
        }

        $effects = $this->presenter->effects($slice, $effect->getEffects());

        return sprintf(
            '%s: %s — %s%s.',
            $attackerName,
            $modifierName,
            $checked ? 'успех: ' : '',
            $effects,
        );
    }
}
