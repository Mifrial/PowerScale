<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Новая строка наложенного длительного эффекта.
 */
final class GameActiveEffectNew
{
    /**
     * Создаёт набор полей.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $sourceType Вид источника.
     * @param string $sourceRuleCode Код карточки источника.
     * @param string|null $applierKind character, npc или null.
     * @param int|null $applierId Id наложившего или null.
     * @param string $targetKind character или npc.
     * @param int $targetId Id цели.
     * @param string $durationType Вид длительности.
     * @param string|null $durationUnit Единица срока.
     * @param int $turnsTotal Срок в тиках.
     * @param int $turnsLeft Остаток тиков.
     * @param array{base: int, size: int}|null $strength Сила эффекта.
     * @param array<string, mixed> $context Контекст источника.
     * @param int $startedRound Раунд наложения.
     * @param string $startedParticipant Участник наложения.
     *
     * @return void
     */
    public function __construct(
        private readonly int $gameId,
        private readonly int $sessionId,
        private readonly int $battleId,
        private readonly string $sourceType,
        private readonly string $sourceRuleCode,
        private readonly ?string $applierKind,
        private readonly ?int $applierId,
        private readonly string $targetKind,
        private readonly int $targetId,
        private readonly string $durationType,
        private readonly ?string $durationUnit,
        private readonly int $turnsTotal,
        private readonly int $turnsLeft,
        private readonly ?array $strength,
        private readonly array $context,
        private readonly int $startedRound,
        private readonly string $startedParticipant,
    ) {
    }

    /**
     * Поля строки для SmartTable.
     *
     * @return array<string, mixed> Поля.
     */
    public function fields(): array
    {
        $fields = [
            'game_id' => $this->gameId,
            'session_id' => $this->sessionId,
            'battle_id' => $this->battleId,
            'source_type' => $this->sourceType,
            'source_rule_code' => $this->sourceRuleCode,
            'target_kind' => $this->targetKind,
            'target_id' => $this->targetId,
            'duration_type' => $this->durationType,
            'turns_total' => $this->turnsTotal,
            'turns_left' => $this->turnsLeft,
            'context' => $this->context,
            'started_round' => $this->startedRound,
            'started_participant' => $this->startedParticipant,
        ];
        if ($this->applierKind !== null && $this->applierId !== null) {
            $fields['applier_kind'] = $this->applierKind;
            $fields['applier_id'] = $this->applierId;
        }

        if ($this->durationUnit !== null) {
            $fields['duration_unit'] = $this->durationUnit;
        }

        if ($this->strength !== null) {
            $fields['strength'] = $this->strength;
        }

        return $fields;
    }
}
