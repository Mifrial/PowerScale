<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Участники удара: снимок листа, документы и контекст формулы.
 *
 * Кэш листа и контекста живёт здесь, а не в правилах удара: один участник
 * версии листа считается один раз за запрос.
 */
final class GameStrikeParticipants
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $sheetCache = [];

    /**
     * @var array<string, FormulaContext>
     */
    private array $contextCache = [];

    /**
     * Принимает листы персонажей и срезы ревизии.
     *
     * @param ICharacters $characters Листы персонажей.
     * @param ICharacterFormulaContexts $formulaContexts Контекст листа.
     * @param ICharacterSheets $sheets Полный пересчёт листа.
     * @param GameStrikeSlices $slices Срезы ревизии.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacters $characters,
        private readonly ICharacterFormulaContexts $formulaContexts,
        private readonly ICharacterSheets $sheets,
        private readonly GameStrikeSlices $slices,
    ) {
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
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    public function characterSnapshot(int $characterId): array
    {
        try {
            $record = $this->characters->get($characterId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike character was not found', $exception);
        }

        return [
            'actualVersion' => $record->getActualVersion(),
            'rulesRevision' => $record->getRulesRevision(),
            'choices' => $record->getChoices(),
            'sheet' => $this->liveSheet($record),
        ];
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
        return $kind . ':' . $id . ':' . $version;
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
        return $this->characters->get($characterId)->getName();
    }

    /**
     * Стойкость цели в том же контексте, что и деление урона.
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
     *
     * @throws GameInvalidException Если стойкость битая.
     * @throws GameNotFoundException Если листа нет.
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
        $slice = $this->slices->slice($spaceId, $rulesRevision);
        $documents = $this->defenderDocuments($defenderKind, $defenderId, $npcSheet, $npcChoices, $sheetVersion);
        $value = $this->formulaContext($slice, $documents['sheet'], $documents['choices'], $documents['key'])
            ->findCharacteristic(GameStrikeCharacteristicCodes::enduranceCode($slice));
        if (!$value instanceof DimensionalNumber) {
            throw new GameInvalidException('Game strike endurance is invalid');
        }

        return $value;
    }

    /**
     * Характеристика листа по коду.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код характеристики.
     *
     * @return DimensionalNumber Значение.
     *
     * @throws GameInvalidException Если характеристики нет.
     */
    public function characteristicOf(array $sheet, string $code): DimensionalNumber
    {
        try {
            $value = $this->formulaContexts->build($sheet)->findCharacteristic($code);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game strike characteristic is invalid', $exception);
        }

        if (!$value instanceof DimensionalNumber) {
            throw new GameInvalidException('Game strike characteristic is invalid');
        }

        return $value;
    }

    /**
     * Возвращает документы атакующего.
     *
     * @param string $kind Вид участника.
     * @param int $id Идентификатор.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC.
     * @param int|null $sheetVersion Версия листа NPC.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameInvalidException Если NPC choices отсутствуют.
     * @throws GameNotFoundException Если персонаж отсутствует.
     */
    public function attackerDocuments(
        string $kind,
        int $id,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion = null,
    ): array {
        if ($kind === 'character') {
            return $this->characterAttackerDocuments($id);
        }

        return $this->npcAttackerDocuments($kind, $id, $npcSheet, $npcChoices, $sheetVersion);
    }

    /**
     * Посчитанный лист с картой семейств.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<string, mixed> Sheet.
     */
    public function computedSheet(CharacterRuleSlice $slice, array $sheet, array $choices): array
    {
        if (is_array($sheet['sheet'] ?? null) && is_array($sheet['choices'] ?? null)) {
            $choices = $sheet['choices'];
            $sheet = $sheet['sheet'];
        }

        return $this->sheets->compute($slice, $choices, $sheet);
    }

    /**
     * Лист и выборы цели.
     *
     * @param string $defenderKind Character или npc.
     * @param int $defenderId Цель.
     * @param array<string, mixed>|null $npcSheet Снимок sheet.
     * @param array<string, mixed>|null $npcChoices Документ choices.
     * @param int|null $sheetVersion Версия листа NPC.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameInvalidException Если снимка NPC нет.
     * @throws GameNotFoundException Если персонажа нет.
     */
    public function defenderDocuments(
        string $defenderKind,
        int $defenderId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion = null,
    ): array {
        if ($defenderKind === 'npc') {
            return $this->npcDefenderDocuments($defenderId, $npcSheet, $npcChoices, $sheetVersion);
        }

        return $this->characterDefenderDocuments($defenderId);
    }

    /**
     * Контекст формулы: закупки листа, база расы, автоматические характеристики.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param string|null $participantKey Ключ участника и версии листа.
     *
     * @return FormulaContext Контекст.
     *
     * @throws CharacterInvalidException Если снимок битый.
     */
    public function formulaContext(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        ?string $participantKey = null,
    ): FormulaContext {
        if ($participantKey !== null && isset($this->contextCache[$participantKey])) {
            return $this->contextCache[$participantKey];
        }

        if (is_array($sheet['sheet'] ?? null) && is_array($sheet['choices'] ?? null)) {
            $choices = $sheet['choices'];
            $sheet = $sheet['sheet'];
        }

        $computed = $this->sheets->compute($slice, $choices, $sheet);
        $context = $this->formulaContexts->build($computed);
        if ($participantKey !== null) {
            $this->sheetCache[$participantKey] = $computed;
            $this->contextCache[$participantKey] = $context;
        }

        return $context;
    }

    /**
     * Документы атакующего-персонажа.
     *
     * @param int $id Персонаж.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    private function characterAttackerDocuments(int $id): array
    {
        $snapshot = $this->characterSnapshot($id);

        return [
            'sheet' => $snapshot['sheet'],
            'choices' => $snapshot['choices'],
            'key' => $this->makeParticipantKey('character', $id, $snapshot['actualVersion']),
        ];
    }

    /**
     * Документы атакующего-NPC.
     *
     * @param string $kind Вид участника.
     * @param int $id Идентификатор.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC.
     * @param int|null $sheetVersion Версия листа NPC.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameInvalidException Если это не NPC или нет sheet.
     */
    private function npcAttackerDocuments(
        string $kind,
        int $id,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion,
    ): array {
        if ($kind !== 'npc' || !is_array($npcSheet)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        return [
            'sheet' => $npcSheet,
            'choices' => $npcChoices,
            'key' => $sheetVersion === null ? null : $this->makeParticipantKey('npc', $id, $sheetVersion),
        ];
    }

    /**
     * Документы цели-NPC.
     *
     * @param int $defenderId Цель.
     * @param array<string, mixed>|null $npcSheet Снимок sheet.
     * @param array<string, mixed>|null $npcChoices Документ choices.
     * @param int|null $sheetVersion Версия листа NPC.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameInvalidException Если нет sheet или choices.
     */
    private function npcDefenderDocuments(
        int $defenderId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion,
    ): array {
        return [
            'sheet' => $this->npcSheet($npcSheet),
            'choices' => $npcChoices ?? throw new GameInvalidException('Game strike npc choices are missing'),
            'key' => $sheetVersion === null ? null : $this->makeParticipantKey('npc', $defenderId, $sheetVersion),
        ];
    }

    /**
     * Документы цели-персонажа.
     *
     * @param int $defenderId Цель.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    private function characterDefenderDocuments(int $defenderId): array
    {
        try {
            $record = $this->characters->get($defenderId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike character was not found', $exception);
        }

        return [
            'sheet' => $this->liveSheet($record),
            'choices' => $record->getChoices(),
            'key' => $this->makeParticipantKey('character', $defenderId, $record->getActualVersion()),
        ];
    }

    /**
     * Посчитанный sheet actual.
     *
     * @param CharacterRecord $record Строка.
     *
     * @return array<string, mixed> Sheet.
     */
    private function liveSheet(CharacterRecord $record): array
    {
        $key = $this->makeParticipantKey('character', $record->getId(), $record->getActualVersion());
        if (isset($this->sheetCache[$key])) {
            return $this->sheetCache[$key];
        }

        $sheet = $this->sheets->compute(
            $this->slices->slice($record->getSpaceId(), $record->getRulesRevision()),
            $record->getChoices(),
            $record->getSheet(),
        );
        $this->sheetCache[$key] = $sheet;
        $this->contextCache[$key] = $this->formulaContexts->build($sheet);

        return $sheet;
    }

    /**
     * Снимок sheet NPC.
     *
     * @param array<string, mixed>|null $npcSheet Снимок или null.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws GameInvalidException Если снимка нет.
     */
    private function npcSheet(?array $npcSheet): array
    {
        if ($npcSheet === null) {
            throw new GameInvalidException('Game strike npc sheet is missing');
        }

        return $npcSheet;
    }
}
