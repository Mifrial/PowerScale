<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Доступ к данным удара: срез ревизии и снимок участника.
 */
final class GameStrikeData
{
    /**
     * Принимает срезы и участников.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeParticipants $participants Участники удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSlices $slices,
        private readonly GameStrikeParticipants $participants,
    ) {
    }

    /**
     * Срез ревизии.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return CharacterRuleSlice Срез.
     */
    public function ruleSlice(int $spaceId, int $rulesRevision): CharacterRuleSlice
    {
        return $this->slices->slice($spaceId, $rulesRevision);
    }

    /**
     * Свежий счётчик и лист персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return array{
     *     actualVersion: int,
     *     rulesRevision: int,
     *     choices: array<string, mixed>,
     *     sheet: array<string, mixed>
     * } Снимок.
     */
    public function characterSnapshot(int $characterId): array
    {
        return $this->participants->characterSnapshot($characterId);
    }

    /**
     * Ключ кэша листа участника.
     *
     * @param string $kind Character или npc.
     * @param int $id Идентификатор.
     * @param int $version ActualVersion строки.
     *
     * @return string Ключ.
     */
    public function makeParticipantKey(string $kind, int $id, int $version): string
    {
        return $this->participants->makeParticipantKey($kind, $id, $version);
    }

    /**
     * Имя персонажа для токена чата.
     *
     * @param int $characterId Персонаж.
     *
     * @return string Имя.
     */
    public function characterName(int $characterId): string
    {
        return $this->participants->characterName($characterId);
    }

    /**
     * Стойкость цели в контексте деления урона.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $defenderKind Вид цели.
     * @param int $defenderId Цель.
     * @param array<string, mixed>|null $npcSheet Лист NPC.
     * @param array<string, mixed>|null $npcChoices Выборы NPC.
     * @param int|null $sheetVersion Версия листа.
     *
     * @return DimensionalNumber Стойкость.
     */
    public function enduranceOf(
        int $spaceId,
        int $rulesRevision,
        string $defenderKind,
        int $defenderId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion = null,
    ): DimensionalNumber {
        return $this->participants->enduranceOf(
            $spaceId,
            $rulesRevision,
            $defenderKind,
            $defenderId,
            $npcSheet,
            $npcChoices,
            $sheetVersion,
        );
    }

    /**
     * Характеристика листа по коду.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код характеристики.
     *
     * @return DimensionalNumber Значение.
     */
    public function characteristicOf(array $sheet, string $code): DimensionalNumber
    {
        return $this->participants->characteristicOf($sheet, $code);
    }
}
