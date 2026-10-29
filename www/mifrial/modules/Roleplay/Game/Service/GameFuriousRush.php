<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\OptionalAfterStrikeCheckEffect;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Опция после удара: проверка, урон по себе и снятие помехи родителя.
 */
final class GameFuriousRush
{
    /**
     * Создаёт расчёт опции.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeResolution $resolution Последствия урона.
     * @param GameStrikeConsequences $consequences Бросок и чат.
     * @param GameStrikeChat $chat Подписи участников.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameStrikeOptions $options Опции карточки.
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
        private readonly GameStrikeOptions $options,
    ) {
    }

    /**
     * Выполняет объявленные опции после удара.
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
     * @return array<string, mixed> Итог опций: коды, бросок, успех, урон, пропуск помехи.
     *
     * @throws GameInvalidException Если код, лист или запись битые.
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
            $option = $this->options->find($slice, $actionRuleCode, $levels, $code);
            $effect = $option['effect'];
            $sheet = $this->sheets->snapshot($game, $attackerKind, $attackerId)['sheet']['sheet'] ?? null;
            if (!is_array($sheet)) {
                throw new GameInvalidException('Game strike sheet is invalid');
            }

            $roll = $this->consequences->optionalCheck(
                $game,
                $sheet,
                $effect->getCheckCode(),
                $effect->getDifficulty(),
            );
            $success = ($roll['success'] ?? false) === true;

            $this->consequences->postRolls($game, $actorUserId, $threadId, [$roll], $speaker, $parentId);
            $this->consequences->postLines(
                $game,
                $actorUserId,
                $threadId,
                [$this->outcomeLine($attackerName, $option['name'], $effect, $success)],
                $speaker,
                $parentId,
            );

            $result['codes'][] = $option['code'];
            $result['roll'] = $roll;
            $result['success'] = $success;
            if (!$success) {
                continue;
            }

            $injury = $this->injuryOf(
                $game,
                $open,
                $attackerKind,
                $attackerId,
                $effect,
                $actionCharacteristicModifier,
                $gripBonus,
            );
            $prepared = $this->resolution->selfDamage(
                $game,
                $attackerKind,
                $attackerId,
                $injury,
                $effect->getSelfDamage()->getDamageTypeCode(),
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
            $result['skipped'] = $result['skipped'] || $effect->isSkipParentPending();
        }

        return $result;
    }

    /**
     * Урон по себе: сила удара, сдвинутая на размер эффекта.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытая строка удара.
     * @param string $attackerKind character или npc.
     * @param int $attackerId Атакующий.
     * @param OptionalAfterStrikeCheckEffect $effect Эффект.
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
        OptionalAfterStrikeCheckEffect $effect,
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
        $shift = $effect->getSelfDamage()->getSizeDelta();

        return $shift === 0 ? $number : $number->modify($shift * CharacteristicNumber::BASE_STEP);
    }

    /**
     * Строка исхода в чат.
     *
     * @param string $attackerName Имя атакующего.
     * @param string $optionName Название опции.
     * @param OptionalAfterStrikeCheckEffect $effect Эффект.
     * @param bool $success Успех проверки.
     *
     * @return string Текст.
     */
    private function outcomeLine(
        string $attackerName,
        string $optionName,
        OptionalAfterStrikeCheckEffect $effect,
        bool $success,
    ): string {
        if (!$success) {
            return sprintf('%s: %s — провал, проверка не пройдена.', $attackerName, $optionName);
        }

        return sprintf(
            '%s: %s — успех, урон по себе%s.',
            $attackerName,
            $optionName,
            $effect->isSkipParentPending() ? ', помеха действия снята' : '',
        );
    }
}
