<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Новая строка поддерживаемого заклинания. Набор присутствующих полей.
 */
final class GameActiveSpellNew
{
    /**
     * Создаёт набор полей.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $casterKind character или npc.
     * @param int $casterId Id кастера.
     * @param string $spellCode Код заклинания.
     * @param string $pathCode Код пути.
     * @param string $sourceKey Ключ источника.
     * @param string $durationType Вид длительности.
     * @param array{base: int, size: int} $usedPower Мощь сотворения.
     * @param array{base: int, size: int} $sustainPower Мощь поддержания.
     * @param array<string, int|array{base: int, size: int}> $parameterValues Параметры.
     * @param array<int, string> $appliedUpgradeCodes Улучшения.
     * @param int $startedRound Раунд создания.
     * @param string $startedParticipant Участник создания.
     * @param int $casterSheetVersion Версия листа после каста.
     *
     * @return void
     */
    public function __construct(
        private readonly int $gameId,
        private readonly int $sessionId,
        private readonly int $battleId,
        private readonly string $casterKind,
        private readonly int $casterId,
        private readonly string $spellCode,
        private readonly string $pathCode,
        private readonly string $sourceKey,
        private readonly string $durationType,
        private readonly array $usedPower,
        private readonly array $sustainPower,
        private readonly array $parameterValues,
        private readonly array $appliedUpgradeCodes,
        private readonly int $startedRound,
        private readonly string $startedParticipant,
        private readonly int $casterSheetVersion,
    ) {
    }

    /**
     * Поля строки для SmartTable.
     *
     * @return array<string, mixed> Поля.
     */
    public function fields(): array
    {
        return [
            'game_id' => $this->gameId,
            'session_id' => $this->sessionId,
            'battle_id' => $this->battleId,
            'caster_kind' => $this->casterKind,
            'caster_id' => $this->casterId,
            'spell_code' => $this->spellCode,
            'path_code' => $this->pathCode,
            'source_key' => $this->sourceKey,
            'duration_type' => $this->durationType,
            'used_power' => $this->usedPower,
            'sustain_power' => $this->sustainPower,
            'parameter_values' => $this->parameterValues,
            'applied_upgrade_codes' => $this->appliedUpgradeCodes,
            'started_round' => $this->startedRound,
            'started_participant' => $this->startedParticipant,
            'caster_sheet_version' => $this->casterSheetVersion,
        ];
    }
}
